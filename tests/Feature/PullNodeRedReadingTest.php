<?php

namespace Tests\Feature;

use App\Models\OeeReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PullNodeRedReadingTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'http://node-red.test/api/oee';

    protected function setUp(): void
    {
        parent::setUp();

        config(['oee.node_red_url' => self::URL]);
        Http::preventStrayRequests();
    }

    public function test_it_stores_the_latest_reading_from_the_legacy_node_red_payload(): void
    {
        Http::fake([self::URL => Http::response([
            ['OEE' => 81.2, 'Availability' => 92, 'Performance' => 89, 'Quality' => 99.2, 'Timestamp' => '2026-10-02 08:00:00'],
            ['OEE' => 10, 'Availability' => 10, 'Performance' => 10, 'Quality' => 10, 'Timestamp' => '2026-10-02 07:59:55'],
        ])]);

        $this->artisan('oee:pull-node-red')->assertSuccessful();

        $reading = OeeReading::sole();
        $this->assertSame(81.2, $reading->oee);
        $this->assertSame('node-red', $reading->source);
        $this->assertSame('2026-10-02 08:00:00', $reading->recorded_at->format('Y-m-d H:i:s'));
    }

    public function test_the_same_timestamp_is_not_stored_twice(): void
    {
        Http::fake([self::URL => Http::response(['availability' => 90, 'performance' => 80, 'quality' => 99, 'timestamp' => '2026-10-02 08:00:00'])]);

        $this->artisan('oee:pull-node-red')->assertSuccessful();
        $this->artisan('oee:pull-node-red')->expectsOutputToContain('Tidak ada pembacaan baru')->assertSuccessful();

        $this->assertDatabaseCount('oee_readings', 1);
    }

    public function test_it_fails_when_node_red_is_unreachable(): void
    {
        Http::fake([self::URL => Http::response('Bad Gateway', 502)]);

        $this->artisan('oee:pull-node-red')->assertFailed();

        $this->assertDatabaseCount('oee_readings', 0);
    }

    public function test_it_rejects_payloads_without_the_oee_pillars(): void
    {
        Http::fake([self::URL => Http::response(['status' => 'ok'])]);

        $this->artisan('oee:pull-node-red')->expectsOutputToContain('tidak memuat')->assertFailed();
    }

    public function test_it_does_nothing_without_a_configured_url(): void
    {
        config(['oee.node_red_url' => null]);

        $this->artisan('oee:pull-node-red')->expectsOutputToContain('OEE_NODE_RED_URL')->assertSuccessful();
    }
}
