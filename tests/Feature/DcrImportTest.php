<?php

namespace Tests\Feature;

use App\Actions\ImportDcrWorkbook;
use App\Models\Machine;
use App\Models\ProductionRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class DcrImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var list<string>
     */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        array_map('unlink', array_filter($this->tempFiles, 'is_file'));

        parent::tearDown();
    }

    public function test_command_imports_packing_rows_and_machines(): void
    {
        $path = $this->makeWorkbook([
            $this->row(['J' => '01', 'O' => 480, 'AG' => 88]),
            $this->row(['J' => 11, 'E' => 2, 'F' => 'Half', 'AW' => null, 'AX' => null, 'M' => '8 0']),
        ]);

        $this->artisan('oee:import-dcr', ['path' => $path])->assertSuccessful();

        $this->assertSame(['01', '11'], Machine::orderBy('code')->pluck('code')->all());
        $this->assertDatabaseHas('machines', ['code' => '01', 'name' => 'Mesin 01', 'type' => 'Kawashima', 'production_house' => 'PH1']);

        $full = ProductionRecord::whereRelation('machine', 'code', '01')->sole();
        $this->assertSame('2026-09-01', $full->production_date->toDateString());
        $this->assertSame(480, $full->actual_cartons);
        $this->assertSame(88, $full->reject_total_pcs);
        $this->assertSame(449.56, $full->planned_minutes);

        $half = ProductionRecord::whereRelation('machine', 'code', '11')->sole();
        $this->assertSame(80, $half->speed_ppm, 'Stray spaces inside numbers are tolerated.');
        $this->assertSame(round(config('oee.default_planned_minutes'), 2), $half->planned_minutes);
        $this->assertEquals(config('oee.half_shift_unavailable_minutes'), $half->unavailable_minutes);
    }

    public function test_reimporting_updates_existing_rows_instead_of_duplicating(): void
    {
        $this->artisan('oee:import-dcr', ['path' => $this->makeWorkbook([$this->row(['O' => 400])])])->assertSuccessful();
        $this->artisan('oee:import-dcr', ['path' => $this->makeWorkbook([$this->row(['O' => 450])])])->assertSuccessful();

        $this->assertSame(450, ProductionRecord::sole()->actual_cartons);
    }

    public function test_formula_error_cells_fall_back_to_defaults(): void
    {
        $path = $this->makeWorkbook([$this->row(['AX' => '#REF!', 'BJ' => 'a'])]);

        $this->artisan('oee:import-dcr', ['path' => $path])->assertSuccessful();

        $record = ProductionRecord::sole();
        $this->assertEquals(0, $record->unavailable_minutes);
        $this->assertEquals(0, $record->downtime_minutes);
    }

    public function test_rows_without_a_date_or_machine_are_skipped(): void
    {
        $path = $this->makeWorkbook([$this->row(), $this->row(['D' => null]), $this->row(['J' => null])]);

        $result = app(ImportDcrWorkbook::class)->handle($path);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(2, $result['skipped']);
        $this->assertDatabaseCount('production_records', 1);
    }

    public function test_command_fails_for_a_workbook_with_unexpected_headers(): void
    {
        $path = $this->makeWorkbook([$this->row()], headers: ['D' => 'Tgl']);

        $this->artisan('oee:import-dcr', ['path' => $path])
            ->expectsOutputToContain('Format DCR tidak dikenali')
            ->assertFailed();

        $this->assertDatabaseCount('production_records', 0);
    }

    public function test_workbook_can_be_uploaded_from_the_dashboard(): void
    {
        $file = new UploadedFile($this->makeWorkbook([$this->row()]), 'DCR September.xlsx', null, null, true);

        $this->post(route('imports.store'), ['workbook' => $file])
            ->assertRedirect(route('dashboard', ['date' => '2026-09-01']))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'DCR September.xlsx berhasil diimpor'));

        $this->assertDatabaseCount('production_records', 1);
    }

    public function test_upload_rejects_non_xlsx_files(): void
    {
        $this->post(route('imports.store'), ['workbook' => UploadedFile::fake()->create('data.csv', 10, 'text/csv')])
            ->assertSessionHasErrors(['workbook' => 'File harus berformat .xlsx.']);
    }

    public function test_upload_reports_a_workbook_without_a_packing_sheet(): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setTitle('Sheet1');
        $file = new UploadedFile($this->save($spreadsheet), 'lain.xlsx', null, null, true);

        $this->post(route('imports.store'), ['workbook' => $file])
            ->assertSessionHasErrors(['workbook' => 'Sheet "Packing" tidak ditemukan. Pastikan file adalah DCR Produksi (Packing).']);
    }

    /**
     * A DCR row with sensible defaults; keys are column letters.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function row(array $overrides = []): array
    {
        return array_replace([
            'D' => ExcelDate::PHPToExcel(new \DateTimeImmutable('2026-09-01')),
            'E' => 1,
            'F' => 'Full',
            'G' => 'Bergantian',
            'H' => 3,
            'I' => 1,
            'J' => '01',
            'K' => 'A00778',
            'L' => 'Taro Net Potato BBQ 60 pack x 8 gr',
            'M' => 80,
            'N' => 576,
            'O' => 408,
            'P' => 195.84,
            'AG' => 0,
            'AK' => 0.48,
            'AN' => 60,
            'AT' => 'Kawashima',
            'AU' => 'PH1',
            'AW' => 449.56,
            'AX' => 0,
            'BJ' => 0,
        ], $overrides);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, string>  $headers
     */
    private function makeWorkbook(array $rows, array $headers = []): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Packing');

        $headers = array_replace(['D' => 'Tanggal', 'E' => 'Shift', 'J' => 'No Mesin', 'O' => 'Vol Aktual Karton (Ctn)'], $headers);
        foreach ($headers as $column => $header) {
            $sheet->setCellValue("{$column}5", $header);
        }

        foreach ($rows as $index => $row) {
            $sheet->setCellValue('A'.(6 + $index), 'KODE'.$index);
            foreach (array_filter($row, fn (mixed $value) => $value !== null) as $column => $value) {
                $sheet->setCellValueExplicit($column.(6 + $index), $value, is_string($value) ? DataType::TYPE_STRING : DataType::TYPE_NUMERIC);
            }
        }

        return $this->save($spreadsheet);
    }

    private function save(Spreadsheet $spreadsheet): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'dcr_'.Str::random(12).'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $this->tempFiles[] = $path;

        return $path;
    }
}
