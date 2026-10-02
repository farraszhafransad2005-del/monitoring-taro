<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\OeeReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OeeReadingApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'rahasia-node-red';

    protected function setUp(): void
    {
        parent::setUp();

        config(['oee.ingest_token' => self::TOKEN]);
    }

    public function test_node_red_can_push_a_reading_for_a_machine(): void
    {
        $machine = Machine::factory()->create(['code' => '03']);

        $this->withToken(self::TOKEN)
            ->postJson(route('api.v1.oee-readings.store'), [
                'machine' => '3',
                'availability' => 90,
                'performance' => 80,
                'quality' => 99.5,
                'speed_ppm' => 78,
            ])
            ->assertCreated()
            ->assertJsonPath('data.machine', '03')
            ->assertJsonPath('data.oee', 71.64)
            ->assertJsonPath('data.source', 'api');

        $this->assertDatabaseHas('oee_readings', ['machine_id' => $machine->id, 'speed_ppm' => 78]);
    }

    public function test_pushing_requires_the_ingest_token(): void
    {
        $payload = ['availability' => 90, 'performance' => 80, 'quality' => 99];

        $this->postJson(route('api.v1.oee-readings.store'), $payload)->assertForbidden();
        $this->withToken('salah')->postJson(route('api.v1.oee-readings.store'), $payload)->assertForbidden();

        $this->assertDatabaseCount('oee_readings', 0);
    }

    public function test_pushing_is_disabled_when_no_token_is_configured(): void
    {
        config(['oee.ingest_token' => null]);

        $this->withToken('')
            ->postJson(route('api.v1.oee-readings.store'), ['availability' => 90, 'performance' => 80, 'quality' => 99])
            ->assertForbidden();
    }

    public function test_pushed_values_are_validated(): void
    {
        $this->withToken(self::TOKEN)
            ->postJson(route('api.v1.oee-readings.store'), ['machine' => '99', 'availability' => 120, 'quality' => 99])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['machine', 'availability', 'performance']);
    }

    public function test_listing_returns_the_latest_readings_of_one_machine_in_chronological_order(): void
    {
        $machine = Machine::factory()->create(['code' => '05']);
        $older = OeeReading::factory()->for($machine)->create(['recorded_at' => now()->subMinutes(2)]);
        $newer = OeeReading::factory()->for($machine)->create(['recorded_at' => now()->subMinute()]);
        OeeReading::factory()->for($machine)->create(['recorded_at' => now()->subMinutes(10)]);
        OeeReading::factory()->create();

        $this->getJson(route('api.v1.oee-readings.index', ['machine' => '5', 'limit' => 2]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $older->id)
            ->assertJsonPath('data.1.id', $newer->id);
    }
}
