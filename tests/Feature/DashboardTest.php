<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\ProductionRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_it_prompts_for_an_import_when_there_is_no_data(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Belum ada data produksi')
            ->assertSee('php artisan oee:import-dcr');
    }

    public function test_it_shows_oee_for_the_latest_production_date_by_default(): void
    {
        $machine = Machine::factory()->create(['code' => '05']);
        $this->recordFor($machine, '2026-09-28', ['actual_cartons' => 100, 'reject_total_pcs' => 0, 'downtime_minutes' => 0]);
        $this->recordFor($machine, '2026-09-29');

        $response = $this->get(route('dashboard'))->assertOk();

        $this->assertSame('2026-09-29', $response->viewData('dashboard')['filters']['date']);
        $response->assertSeeInOrder(['OEE Keseluruhan', '79.4%']);
        $response->assertSeeInOrder(['Availability', '90.0%']);
        $response->assertSeeInOrder(['Performance', '88.9%']);
        $response->assertSeeInOrder(['Quality Rate', '99.3%']);
    }

    public function test_shift_filter_limits_the_summary_to_that_shift(): void
    {
        $machine = Machine::factory()->create();
        $this->recordFor($machine, '2026-09-29', ['shift' => 1]);
        $this->recordFor($machine, '2026-09-29', ['shift' => 2, 'actual_cartons' => 270, 'downtime_minutes' => 0, 'reject_total_pcs' => 0]);

        $summary = $this->get(route('dashboard', ['shift' => 2]))->assertOk()->viewData('dashboard')['summary'];

        $this->assertSame(100.0, $summary['avail']);
        $this->assertSame(45.0, $summary['perf']); // 270 × 60 ÷ (450 × 80)
        $this->assertSame(1, $summary['records']);
    }

    public function test_dates_outside_the_imported_range_are_clamped(): void
    {
        $this->recordFor(Machine::factory()->create(), '2026-09-29');

        $filters = $this->get(route('dashboard', ['date' => '2030-01-01']))->viewData('dashboard')['filters'];

        $this->assertSame('2026-09-29', $filters['date']);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->get(route('dashboard', ['shift' => 4, 'date' => '29-09-2026']))
            ->assertSessionHasErrors(['shift', 'date']);
    }

    public function test_item_names_from_the_workbook_are_escaped(): void
    {
        $this->recordFor(Machine::factory()->create(), '2026-09-29', ['item' => '<script>alert(1)</script>']);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', escape: false);
    }

    public function test_csv_export_lists_each_machine_for_the_selected_date(): void
    {
        $machine = Machine::factory()->create(['code' => '07', 'name' => 'Mesin 07']);
        $this->recordFor($machine, '2026-09-29', ['item' => 'Taro Net Seaweed 60 pack x 8 gr']);

        $response = $this->get(route('dashboard.export', ['date' => '2026-09-29']))
            ->assertOk()
            ->assertDownload('FKS_Food_OEE_2026-09-29.csv');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('"Mesin 07",', $csv);
        $this->assertStringContainsString('"Taro Net Seaweed 60 pack x 8 gr"', $csv);
        $this->assertStringContainsString('OEE,79.4,85', $csv);
    }

    /**
     * A record whose pillars are A 90%, P 88.9%, Q 99.3% → OEE 79.4% unless overridden.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function recordFor(Machine $machine, string $date, array $attributes = []): ProductionRecord
    {
        return ProductionRecord::factory()->for($machine)->create([
            'production_date' => $date,
            'shift' => 1,
            'speed_ppm' => 80,
            'pcs_per_carton' => 60,
            'planned_minutes' => 450,
            'unavailable_minutes' => 0,
            'downtime_minutes' => 45,
            'actual_cartons' => 480,
            'reject_total_pcs' => 200,
            ...$attributes,
        ]);
    }
}
