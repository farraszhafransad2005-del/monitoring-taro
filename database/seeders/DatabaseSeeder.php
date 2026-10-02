<?php

namespace Database\Seeders;

use App\Models\Machine;
use App\Models\ProductionRecord;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed from the DCR workbooks in storage/app/imports, or with sample data when there are none.
     */
    public function run(): void
    {
        if (glob(config('oee.imports_path').DIRECTORY_SEPARATOR.'*.xlsx')) {
            $this->command->call('oee:import-dcr');

            return;
        }

        Machine::factory(9)->create()->each(function (Machine $machine): void {
            foreach (range(0, 13) as $daysAgo) {
                foreach ([1, 2, 3] as $shift) {
                    ProductionRecord::factory()->for($machine)->create([
                        'production_date' => now()->subDays($daysAgo)->toDateString(),
                        'shift' => $shift,
                    ]);
                }
            }
        });
    }
}
