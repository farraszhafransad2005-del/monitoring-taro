<?php

namespace App\Console\Commands;

use App\Models\Machine;
use App\Models\OeeReading;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

#[Signature('oee:pull-node-red')]
#[Description('Ambil pembacaan OEE terbaru dari endpoint Node-RED (OEE_NODE_RED_URL) dan simpan ke database')]
class PullNodeRedReadingCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $url = config('oee.node_red_url');

        if (blank($url)) {
            $this->components->warn('OEE_NODE_RED_URL belum diisi di .env.');

            return self::SUCCESS;
        }

        try {
            $payload = Http::timeout(4)->acceptJson()->get($url)->throw()->json();
        } catch (ConnectionException|RequestException $exception) {
            $this->components->error('Node-RED tidak dapat dihubungi: '.$exception->getMessage());

            return self::FAILURE;
        }

        $data = array_is_list((array) $payload) ? ($payload[0] ?? []) : (array) $payload;

        $metric = function (string ...$keys) use ($data): ?float {
            foreach ($keys as $key) {
                if (is_numeric($data[$key] ?? null)) {
                    return (float) $data[$key];
                }
            }

            return null;
        };

        $availability = $metric('availability', 'Availability', 'avail');
        $performance = $metric('performance', 'Performance', 'perf');
        $quality = $metric('quality', 'Quality', 'qual');

        if ($availability === null || $performance === null || $quality === null) {
            $this->components->error('Respons Node-RED tidak memuat availability/performance/quality.');

            return self::FAILURE;
        }

        $recordedAt = isset($data['timestamp']) || isset($data['Timestamp'])
            ? Carbon::parse($data['timestamp'] ?? $data['Timestamp'])
            : now();

        $machineCode = $data['machine'] ?? $data['mesin'] ?? null;

        $reading = OeeReading::firstOrCreate(
            [
                'source' => 'node-red',
                'recorded_at' => $recordedAt,
                'machine_id' => $machineCode !== null ? Machine::where('code', Machine::normalizeCode($machineCode))->value('id') : null,
            ],
            [
                'availability' => $availability,
                'performance' => $performance,
                'quality' => $quality,
                'oee' => $metric('oee', 'OEE') ?? round($availability * $performance * $quality / 10000, 2),
                'speed_ppm' => $metric('speed_ppm', 'ppm', 'speed'),
                'total_count' => $metric('total_count', 'total'),
                'good_count' => $metric('good_count', 'good'),
                'reject_count' => $metric('reject_count', 'reject'),
            ],
        );

        $this->components->info($reading->wasRecentlyCreated ? 'Pembacaan baru disimpan.' : 'Tidak ada pembacaan baru.');

        return self::SUCCESS;
    }
}
