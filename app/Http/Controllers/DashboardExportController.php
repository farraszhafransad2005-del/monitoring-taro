<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardFilterRequest;
use App\Services\OeeDashboard;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardExportController extends Controller
{
    /**
     * Download the dashboard numbers for the selected date and shift as CSV.
     */
    public function __invoke(DashboardFilterRequest $request, OeeDashboard $dashboard): StreamedResponse
    {
        $data = $dashboard->build($request->validated('date'), $request->shift());
        $date = $data['filters']['date'] ?? now()->toDateString();
        $shift = $data['filters']['shift'];

        return response()->streamDownload(function () use ($data, $date, $shift): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\u{FEFF}");

            $summary = $data['summary'];
            $targets = $data['targets'];

            fputcsv($out, ['LAPORAN DATA OEE — FKS FOOD LINI PACKAGING']);
            fputcsv($out, ['Tanggal Produksi', $date]);
            fputcsv($out, ['Shift', $shift ? config("oee.shifts.{$shift}") : 'Semua Shift']);
            fputcsv($out, ['Diekspor', now()->toDateTimeString()]);
            fputcsv($out, []);

            fputcsv($out, ['Parameter', 'Nilai (%)', 'Target (%)']);
            fputcsv($out, ['OEE', $summary['oee'], $targets['oee']]);
            fputcsv($out, ['Availability', $summary['avail'], $targets['availability']]);
            fputcsv($out, ['Performance', $summary['perf'], $targets['performance']]);
            fputcsv($out, ['Quality', $summary['qual'], $targets['quality']]);
            fputcsv($out, []);

            fputcsv($out, ['Mesin', 'Tipe', 'SKU', 'Operating (menit)', 'Downtime (menit)', 'Ideal Speed (PPM)', 'Ideal Output (pcs)', 'Actual Output (pcs)', 'Reject (pcs)', 'Good (kg)', 'Availability (%)', 'Performance (%)', 'Quality (%)', 'OEE (%)']);
            foreach ($data['machines'] as $machine) {
                fputcsv($out, [
                    $machine['name'], $machine['type'], $machine['sku'], $machine['operatingMinutes'], $machine['downtimeMinutes'],
                    $machine['idealSpeed'], $machine['idealPcs'], $machine['actualPcs'], $machine['rejectPcs'], $machine['goodKg'],
                    $machine['avail'], $machine['perf'], $machine['qual'], $machine['oee'],
                ]);
            }
            fputcsv($out, []);

            fputcsv($out, ['Kategori Afal Etiket', 'Pcs', '% dari total']);
            foreach ($data['losses']['items'] as $loss) {
                fputcsv($out, [$loss['label'], $loss['pcs'], $loss['share']]);
            }

            fclose($out);
        }, "FKS_Food_OEE_{$date}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
