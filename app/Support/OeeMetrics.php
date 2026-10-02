<?php

namespace App\Support;

use App\Models\ProductionRecord;

/**
 * Additive OEE building blocks for one or more production records.
 *
 * Availability = Operating Time / Available Time
 * Performance  = Actual Output / (Operating Time × Ideal Speed)
 * Quality      = Good Output / (Good Output + Reject)
 * OEE          = Availability × Performance × Quality
 */
final readonly class OeeMetrics
{
    public function __construct(
        public float $availableMinutes = 0,
        public float $downtimeMinutes = 0,
        public float $idealPcs = 0,
        public float $actualPcs = 0,
        public float $rejectPcs = 0,
        public float $goodKg = 0,
        public int $recordCount = 0,
    ) {}

    /**
     * Build from a row selected with {@see ProductionRecord::oeeAggregateSelect()}.
     */
    public static function fromAggregate(object $row): self
    {
        return new self(
            availableMinutes: (float) ($row->available_minutes ?? 0),
            downtimeMinutes: (float) ($row->downtime_minutes ?? 0),
            idealPcs: (float) ($row->ideal_pcs ?? 0),
            actualPcs: (float) ($row->actual_pcs ?? 0),
            rejectPcs: (float) ($row->reject_pcs ?? 0),
            goodKg: (float) ($row->good_kg ?? 0),
            recordCount: (int) ($row->record_count ?? 0),
        );
    }

    /**
     * @param  iterable<self>  $metrics
     */
    public static function sum(iterable $metrics): self
    {
        $total = new self;

        foreach ($metrics as $metric) {
            $total = $total->merge($metric);
        }

        return $total;
    }

    public function merge(self $other): self
    {
        return new self(
            availableMinutes: $this->availableMinutes + $other->availableMinutes,
            downtimeMinutes: $this->downtimeMinutes + $other->downtimeMinutes,
            idealPcs: $this->idealPcs + $other->idealPcs,
            actualPcs: $this->actualPcs + $other->actualPcs,
            rejectPcs: $this->rejectPcs + $other->rejectPcs,
            goodKg: $this->goodKg + $other->goodKg,
            recordCount: $this->recordCount + $other->recordCount,
        );
    }

    public function operatingMinutes(): float
    {
        return max(0, $this->availableMinutes - $this->downtimeMinutes);
    }

    public function totalPcs(): float
    {
        return $this->actualPcs + $this->rejectPcs;
    }

    /**
     * Rate between 0 and 1.
     */
    public function availability(): float
    {
        return $this->ratio($this->operatingMinutes(), $this->availableMinutes);
    }

    /**
     * Rate between 0 and 1. Capped at 1 when actual output beats the ideal speed.
     */
    public function performance(): float
    {
        return $this->ratio($this->actualPcs, $this->idealPcs);
    }

    /**
     * Rate between 0 and 1.
     */
    public function quality(): float
    {
        return $this->ratio($this->actualPcs, $this->totalPcs());
    }

    public function oee(): float
    {
        return $this->availability() * $this->performance() * $this->quality();
    }

    public function isEmpty(): bool
    {
        return $this->recordCount === 0;
    }

    /**
     * @return array{oee: float, avail: float, perf: float, qual: float, availableMinutes: float, downtimeMinutes: float, operatingMinutes: float, idealPcs: int, actualPcs: int, rejectPcs: int, totalPcs: int, goodKg: float, records: int}
     */
    public function toArray(): array
    {
        return [
            'oee' => $this->percent($this->oee()),
            'avail' => $this->percent($this->availability()),
            'perf' => $this->percent($this->performance()),
            'qual' => $this->percent($this->quality()),
            'availableMinutes' => round($this->availableMinutes, 1),
            'downtimeMinutes' => round($this->downtimeMinutes, 1),
            'operatingMinutes' => round($this->operatingMinutes(), 1),
            'idealPcs' => (int) round($this->idealPcs),
            'actualPcs' => (int) round($this->actualPcs),
            'rejectPcs' => (int) round($this->rejectPcs),
            'totalPcs' => (int) round($this->totalPcs()),
            'goodKg' => round($this->goodKg, 1),
            'records' => $this->recordCount,
        ];
    }

    private function ratio(float $numerator, float $denominator): float
    {
        if ($denominator <= 0) {
            return 0.0;
        }

        return min(1.0, max(0.0, $numerator / $denominator));
    }

    private function percent(float $rate): float
    {
        return round($rate * 100, 1);
    }
}
