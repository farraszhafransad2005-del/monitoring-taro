<?php

namespace Tests\Unit;

use App\Support\OeeMetrics;
use PHPUnit\Framework\TestCase;

class OeeMetricsTest extends TestCase
{
    public function test_it_computes_the_three_pillars_and_oee(): void
    {
        $metrics = new OeeMetrics(
            availableMinutes: 450,
            downtimeMinutes: 45,
            idealPcs: 405 * 80,
            actualPcs: 28_800,
            rejectPcs: 200,
            recordCount: 1,
        );

        $this->assertSame(405.0, $metrics->operatingMinutes());
        $this->assertEqualsWithDelta(0.9, $metrics->availability(), 0.0001);
        $this->assertEqualsWithDelta(28_800 / 32_400, $metrics->performance(), 0.0001);
        $this->assertEqualsWithDelta(28_800 / 29_000, $metrics->quality(), 0.0001);
        $this->assertSame(79.4, $metrics->toArray()['oee']);
    }

    public function test_rates_are_capped_at_one_hundred_percent(): void
    {
        $metrics = new OeeMetrics(availableMinutes: 100, idealPcs: 1_000, actualPcs: 1_200, recordCount: 1);

        $this->assertSame(1.0, $metrics->performance());
    }

    public function test_empty_metrics_yield_zero_instead_of_dividing_by_zero(): void
    {
        $metrics = new OeeMetrics;

        $this->assertTrue($metrics->isEmpty());
        $this->assertSame(0.0, $metrics->oee());
    }

    public function test_summing_aggregates_before_dividing(): void
    {
        $fast = new OeeMetrics(availableMinutes: 100, idealPcs: 1_000, actualPcs: 900, recordCount: 1);
        $slow = new OeeMetrics(availableMinutes: 100, idealPcs: 3_000, actualPcs: 1_500, recordCount: 1);

        $total = OeeMetrics::sum([$fast, $slow]);

        // Weighted by ideal output (2400 / 4000), not the average of 90% and 50%.
        $this->assertEqualsWithDelta(0.6, $total->performance(), 0.0001);
        $this->assertSame(2, $total->recordCount);
    }
}
