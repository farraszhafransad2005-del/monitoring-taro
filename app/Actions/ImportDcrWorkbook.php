<?php

namespace App\Actions;

use App\Models\Machine;
use App\Models\ProductionRecord;
use App\Support\OeeMetrics;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Imports the "Packing" sheet of a DCR Produksi workbook into production_records.
 *
 * Only raw input cells are trusted; derived OEE columns in the workbook contain #REF! errors
 * and are recomputed by {@see OeeMetrics}. Re-importing the same workbook
 * updates existing rows instead of duplicating them.
 */
class ImportDcrWorkbook
{
    public const SHEET = 'Packing';

    private const HEADER_ROW = 5;

    private const FIRST_DATA_ROW = 6;

    private const LAST_COLUMN = 'BJ';

    /**
     * Source column letter for each production_records attribute.
     *
     * @var array<string, string>
     */
    private const COLUMNS = [
        'production_date' => 'D',
        'shift' => 'E',
        'shift_type' => 'F',
        'break_type' => 'G',
        'crew_group' => 'H',
        'line' => 'I',
        'machine_code' => 'J',
        'part_no' => 'K',
        'item' => 'L',
        'speed_ppm' => 'M',
        'target_cartons' => 'N',
        'actual_cartons' => 'O',
        'actual_kg' => 'P',
        'cake_usage_kg' => 'Q',
        'carton_reject_total' => 'V',
        'reject_setup' => 'X',
        'reject_roll_change' => 'Y',
        'reject_post_repair_check' => 'Z',
        'reject_empty_pack' => 'AA',
        'reject_bad_coding' => 'AB',
        'reject_leaking_pack' => 'AC',
        'reject_trapped_product' => 'AD',
        'reject_overlap' => 'AE',
        'reject_emark' => 'AF',
        'reject_total_pcs' => 'AG',
        'kg_per_carton' => 'AK',
        'pcs_per_carton' => 'AN',
        'machine_type' => 'AT',
        'production_house' => 'AU',
        'planned_minutes' => 'AW',
        'unavailable_minutes' => 'AX',
        'downtime_minutes' => 'BJ',
    ];

    /**
     * @return array{imported: int, skipped: int, machines: int, from: ?string, to: ?string}
     *
     * @throws InvalidArgumentException when the file is not a DCR Packing workbook.
     */
    public function handle(string $path): array
    {
        $sheet = $this->loadSheet($path);
        $this->assertHeaders($sheet);

        $rows = [];
        $machineAttributes = [];
        $skipped = 0;

        for ($row = self::FIRST_DATA_ROW, $last = $sheet->getHighestDataRow(); $row <= $last; $row++) {
            $values = $this->readRow($sheet, $row);

            if ($values === null) {
                $skipped += $this->isBlankRow($sheet, $row) ? 0 : 1;

                continue;
            }

            $machineAttributes[$values['machine_code']] = array_filter([
                'type' => $values['machine_type'],
                'production_house' => $values['production_house'],
            ]) + ($machineAttributes[$values['machine_code']] ?? []);

            $rows[] = $values;
        }

        return DB::transaction(function () use ($rows, $machineAttributes, $skipped): array {
            $machineIds = $this->syncMachines($machineAttributes);
            $now = now();

            $records = array_map(fn (array $values): array => [
                'machine_id' => $machineIds[$values['machine_code']],
                'created_at' => $now,
                'updated_at' => $now,
            ] + array_diff_key($values, array_flip(['machine_code', 'machine_type', 'production_house'])), $rows);

            foreach (array_chunk($records, 200) as $chunk) {
                ProductionRecord::upsert(
                    $chunk,
                    uniqueBy: ['production_date', 'shift', 'machine_id', 'part_no', 'shift_type'],
                    update: array_values(array_diff(array_keys($chunk[0]), ['production_date', 'shift', 'machine_id', 'part_no', 'shift_type', 'created_at'])),
                );
            }

            $dates = array_column($rows, 'production_date');

            return [
                'imported' => count($records),
                'skipped' => $skipped,
                'machines' => count($machineIds),
                'from' => $dates === [] ? null : min($dates),
                'to' => $dates === [] ? null : max($dates),
            ];
        });
    }

    private function loadSheet(string $path): Worksheet
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("File tidak ditemukan: {$path}");
        }

        try {
            $reader = IOFactory::createReader('Xlsx');
            $sheetNames = $reader->listWorksheetNames($path);
        } catch (SpreadsheetException) {
            throw new InvalidArgumentException('File tidak dapat dibaca sebagai workbook Excel (.xlsx).');
        }

        if (! in_array(self::SHEET, $sheetNames, true)) {
            throw new InvalidArgumentException('Sheet "'.self::SHEET.'" tidak ditemukan. Pastikan file adalah DCR Produksi (Packing).');
        }

        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([self::SHEET]);
        $reader->setReadFilter(new class(Coordinate::columnIndexFromString(self::LAST_COLUMN)) implements IReadFilter
        {
            public function __construct(private int $lastColumnIndex) {}

            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return Coordinate::columnIndexFromString($columnAddress) <= $this->lastColumnIndex;
            }
        });

        return $reader->load($path)->getSheetByName(self::SHEET);
    }

    private function assertHeaders(Worksheet $sheet): void
    {
        $expected = ['D' => 'tanggal', 'E' => 'shift', 'J' => 'no mesin', 'O' => 'vol aktual karton (ctn)'];

        foreach ($expected as $column => $header) {
            $actual = strtolower(trim((string) $this->cell($sheet, $column, self::HEADER_ROW)));

            if ($actual !== $header) {
                throw new InvalidArgumentException("Format DCR tidak dikenali: kolom {$column}".self::HEADER_ROW." seharusnya \"{$header}\".");
            }
        }
    }

    /**
     * @return array<string, mixed>|null Null when the row lacks the fields needed to identify it.
     */
    private function readRow(Worksheet $sheet, int $row): ?array
    {
        $raw = [];

        foreach (self::COLUMNS as $attribute => $column) {
            $raw[$attribute] = $this->cell($sheet, $column, $row);
        }

        $date = $this->toDate($raw['production_date']);
        $shift = $this->toNumber($raw['shift']);
        $machineCode = $this->toText($raw['machine_code']);
        $partNo = $this->toText($raw['part_no']);

        if ($date === null || $shift === null || $machineCode === null || $partNo === null) {
            return null;
        }

        $shiftType = $this->toText($raw['shift_type']) ?? 'Full';
        $planned = $this->toNumber($raw['planned_minutes']) ?? (float) config('oee.default_planned_minutes');
        $unavailable = $this->toNumber($raw['unavailable_minutes'])
            ?? ($shiftType === 'Half' ? (float) config('oee.half_shift_unavailable_minutes') : 0.0);

        $values = [
            'production_date' => $date,
            'shift' => (int) $shift,
            'shift_type' => $shiftType,
            'break_type' => $this->toText($raw['break_type']),
            'crew_group' => $this->toText($raw['crew_group']),
            'line' => $this->toText($raw['line']),
            'machine_code' => Machine::normalizeCode($machineCode),
            'part_no' => $partNo,
            'item' => $this->toText($raw['item']) ?? $partNo,
            'speed_ppm' => $this->toInteger($raw['speed_ppm']),
            'pcs_per_carton' => $this->toInteger($raw['pcs_per_carton']),
            'kg_per_carton' => $this->toNumber($raw['kg_per_carton']),
            'planned_minutes' => round($planned, 2),
            'unavailable_minutes' => round(min($unavailable, $planned), 2),
            'downtime_minutes' => round($this->toNumber($raw['downtime_minutes']) ?? 0.0, 2),
            'target_cartons' => $this->toNumber($raw['target_cartons']),
            'actual_cartons' => $this->toInteger($raw['actual_cartons']) ?? 0,
            'actual_kg' => $this->toNumber($raw['actual_kg']) ?? 0.0,
            'cake_usage_kg' => $this->toNumber($raw['cake_usage_kg']),
            'carton_reject_total' => $this->toInteger($raw['carton_reject_total']) ?? 0,
            'machine_type' => $this->toMachineType($raw['machine_type']),
            'production_house' => $this->toProductionHouse($raw['production_house']),
        ];

        foreach ([...array_keys(ProductionRecord::REJECT_CATEGORIES), 'reject_total_pcs'] as $rejectColumn) {
            $values[$rejectColumn] = $this->toInteger($raw[$rejectColumn]) ?? 0;
        }

        return $values;
    }

    /**
     * @param  array<string, array{type?: string, production_house?: string}>  $machineAttributes
     * @return array<string, int> Machine ids keyed by code.
     */
    private function syncMachines(array $machineAttributes): array
    {
        $ids = [];

        foreach ($machineAttributes as $code => $attributes) {
            $machine = Machine::updateOrCreate(['code' => $code], ['name' => "Mesin {$code}"] + $attributes);
            $ids[$code] = $machine->id;
        }

        return $ids;
    }

    /**
     * The cached value of a formula cell, or the literal value otherwise.
     */
    private function cell(Worksheet $sheet, string $column, int $row): mixed
    {
        if (! $sheet->cellExists($column.$row)) {
            return null;
        }

        $cell = $sheet->getCell($column.$row);

        return $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();
    }

    private function isBlankRow(Worksheet $sheet, int $row): bool
    {
        foreach (['A', 'D', 'J', 'K'] as $column) {
            if ($this->toText($this->cell($sheet, $column, $row)) !== null) {
                return false;
            }
        }

        return true;
    }

    private function toDate(mixed $value): ?string
    {
        return match (true) {
            $value instanceof DateTimeInterface => CarbonImmutable::instance($value)->toDateString(),
            is_numeric($value) && $value > 0 => CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString(),
            is_string($value) && strtotime($value) !== false => CarbonImmutable::parse($value)->toDateString(),
            default => null,
        };
    }

    /**
     * Parse a numeric cell, tolerating stray spaces ("8 0") and rejecting error values ("#REF!").
     */
    private function toNumber(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = preg_replace('/\s+/', '', (string) $value);

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function toInteger(mixed $value): ?int
    {
        $number = $this->toNumber($value);

        return $number === null ? null : max(0, (int) round($number));
    }

    private function toText(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function toMachineType(mixed $value): ?string
    {
        $text = $this->toText($value);

        return $text !== null && ! is_numeric($text) ? $text : null;
    }

    private function toProductionHouse(mixed $value): ?string
    {
        $text = $this->toText($value);

        return $text !== null && preg_match('/^PH\d+$/i', $text) ? strtoupper($text) : null;
    }
}
