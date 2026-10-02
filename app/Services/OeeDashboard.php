<?php

namespace App\Services;

use App\Models\Machine;
use App\Models\ProductionRecord;
use App\Support\OeeMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Builds the data shown on the OEE dashboard from imported production records.
 */
class OeeDashboard
{
    /**
     * @return array<string, mixed>
     */
    public function build(?string $date = null, ?int $shift = null): array
    {
        $range = ProductionRecord::query()
            ->selectRaw('MIN(production_date) as min_date, MAX(production_date) as max_date, MAX(updated_at) as last_import')
            ->first();

        $maxDate = $range?->max_date ? CarbonImmutable::parse($range->max_date)->toDateString() : null;
        $minDate = $range?->min_date ? CarbonImmutable::parse($range->min_date)->toDateString() : null;
        $selectedDate = $this->clampDate($date, $minDate, $maxDate);

        $daily = $this->dailyMachineMetrics($shift);
        $selected = $daily->where('date', $selectedDate);
        $summary = OeeMetrics::sum($selected->pluck('metrics'));

        return [
            'filters' => [
                'date' => $selectedDate,
                'shift' => $shift,
                'minDate' => $minDate,
                'maxDate' => $maxDate,
                'shifts' => config('oee.shifts'),
            ],
            'targets' => config('oee.targets'),
            'lastImport' => $range?->last_import,
            'summary' => $summary->toArray() + ['machineCount' => $selected->pluck('machineId')->unique()->count()],
            'period' => OeeMetrics::sum($daily->pluck('metrics'))->toArray(),
            'machines' => $this->machineBreakdown($selected, $selectedDate, $shift),
            'trend' => $this->trend($daily),
            'heatmap' => $this->weeklyHeatmap($daily),
            'losses' => $this->rejectLosses($selectedDate, $shift),
        ];
    }

    /**
     * Metrics per (date, machine), the grain every dashboard section is built from.
     *
     * @return Collection<int, array{date: string, machineId: int, metrics: OeeMetrics}>
     */
    private function dailyMachineMetrics(?int $shift): Collection
    {
        return ProductionRecord::query()
            ->measurable()
            ->when($shift, fn (Builder $query) => $query->where('shift', $shift))
            ->selectRaw('production_date, machine_id, '.ProductionRecord::oeeAggregateSelect())
            ->groupBy('production_date', 'machine_id')
            ->orderBy('production_date')
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'date' => CarbonImmutable::parse($row->production_date)->toDateString(),
                'machineId' => (int) $row->machine_id,
                'metrics' => OeeMetrics::fromAggregate($row),
            ]);
    }

    /**
     * @param  Collection<int, array{date: string, machineId: int, metrics: OeeMetrics}>  $selected
     * @return list<array<string, mixed>>
     */
    private function machineBreakdown(Collection $selected, ?string $date, ?int $shift): array
    {
        if ($date === null) {
            return [];
        }

        $machines = Machine::query()->whereIn('id', $selected->pluck('machineId'))->orderBy('code')->get();
        $topItems = $this->topItemPerMachine($date, $shift);

        return $machines->map(function (Machine $machine) use ($selected, $topItems): array {
            $metrics = OeeMetrics::sum($selected->where('machineId', $machine->id)->pluck('metrics'));
            $operatingMinutes = $metrics->operatingMinutes();

            return [
                'id' => $machine->id,
                'code' => $machine->code,
                'name' => $machine->name,
                'type' => $machine->type ?? 'Lainnya',
                'productionHouse' => $machine->production_house,
                'sku' => $topItems[$machine->id] ?? '-',
                'idealSpeed' => $operatingMinutes > 0 ? round($metrics->idealPcs / $operatingMinutes, 1) : 0,
                'actualSpeed' => $operatingMinutes > 0 ? round($metrics->actualPcs / $operatingMinutes, 1) : 0,
            ] + $metrics->toArray();
        })->values()->all();
    }

    /**
     * The item with the highest carton output per machine, used as the SKU label.
     *
     * @return array<int, string>
     */
    private function topItemPerMachine(string $date, ?int $shift): array
    {
        return ProductionRecord::query()
            ->whereDate('production_date', $date)
            ->when($shift, fn (Builder $query) => $query->where('shift', $shift))
            ->selectRaw('machine_id, item, SUM(actual_cartons) as cartons')
            ->groupBy('machine_id', 'item')
            ->orderByDesc('cartons')
            ->toBase()
            ->get()
            ->unique('machine_id')
            ->mapWithKeys(fn (object $row): array => [(int) $row->machine_id => $row->item])
            ->all();
    }

    /**
     * @param  Collection<int, array{date: string, machineId: int, metrics: OeeMetrics}>  $daily
     * @return list<array{date: string, oee: float, avail: float, perf: float, qual: float}>
     */
    private function trend(Collection $daily): array
    {
        return $daily->groupBy('date')
            ->map(function (Collection $rows, string $date): array {
                $metrics = OeeMetrics::sum($rows->pluck('metrics'))->toArray();

                return ['date' => $date] + array_intersect_key($metrics, array_flip(['oee', 'avail', 'perf', 'qual']));
            })
            ->values()
            ->all();
    }

    /**
     * OEE per machine per ISO week.
     *
     * @param  Collection<int, array{date: string, machineId: int, metrics: OeeMetrics}>  $daily
     * @return array{weeks: list<array{key: string, label: string}>, rows: list<array{machine: string, values: list<float|null>, average: float}>}
     */
    private function weeklyHeatmap(Collection $daily): array
    {
        $weekOf = fn (string $date): string => CarbonImmutable::parse($date)->startOfWeek()->toDateString();

        $weeks = $daily->pluck('date')->map($weekOf)->unique()->sort()->values();
        $machines = Machine::query()->whereIn('id', $daily->pluck('machineId')->unique())->orderBy('code')->get();

        return [
            'weeks' => $weeks->map(fn (string $week): array => [
                'key' => $week,
                'label' => CarbonImmutable::parse($week)->locale('id')->translatedFormat('d M'),
            ])->all(),
            'rows' => $machines->map(function (Machine $machine) use ($daily, $weeks, $weekOf): array {
                $machineRows = $daily->where('machineId', $machine->id);

                return [
                    'machine' => $machine->code,
                    'values' => $weeks->map(function (string $week) use ($machineRows, $weekOf): ?float {
                        $metrics = OeeMetrics::sum($machineRows->filter(fn (array $row): bool => $weekOf($row['date']) === $week)->pluck('metrics'));

                        return $metrics->isEmpty() ? null : round($metrics->oee() * 100, 1);
                    })->all(),
                    'average' => round(OeeMetrics::sum($machineRows->pluck('metrics'))->oee() * 100, 1),
                ];
            })->all(),
        ];
    }

    /**
     * Etiket reject (afal) per category, largest first.
     *
     * @return array{items: list<array{label: string, pcs: int, share: float}>, total: int}
     */
    private function rejectLosses(?string $date, ?int $shift): array
    {
        $columns = array_keys(ProductionRecord::REJECT_CATEGORIES);

        $sums = ProductionRecord::query()
            ->whereDate('production_date', $date ?? '1900-01-01')
            ->when($shift, fn (Builder $query) => $query->where('shift', $shift))
            ->selectRaw(implode(', ', array_map(fn (string $column): string => "SUM({$column}) as {$column}", $columns)))
            ->toBase()
            ->first();

        $total = array_sum(array_map(fn (string $column): int => (int) ($sums->{$column} ?? 0), $columns));

        $items = collect(ProductionRecord::REJECT_CATEGORIES)
            ->map(fn (string $label, string $column): array => [
                'label' => $label,
                'pcs' => (int) ($sums->{$column} ?? 0),
                'share' => $total > 0 ? round(($sums->{$column} ?? 0) / $total * 100, 1) : 0.0,
            ])
            ->sortByDesc('pcs')
            ->values()
            ->all();

        return ['items' => $items, 'total' => $total];
    }

    private function clampDate(?string $date, ?string $minDate, ?string $maxDate): ?string
    {
        if ($date === null || $minDate === null || $maxDate === null) {
            return $maxDate;
        }

        return max($minDate, min($maxDate, $date));
    }
}
