<?php

namespace App\Http\Controllers;

use App\Actions\ImportDcrWorkbook;
use App\Http\Requests\StoreDcrImportRequest;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class DcrImportController extends Controller
{
    public function store(StoreDcrImportRequest $request, ImportDcrWorkbook $importer): RedirectResponse
    {
        $file = $request->file('workbook');

        try {
            $result = $importer->handle($file->getRealPath());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['workbook' => $exception->getMessage()]);
        }

        return redirect()
            ->route('dashboard', array_filter(['date' => $result['to']]))
            ->with('status', sprintf(
                '%s berhasil diimpor: %s baris, %d mesin (%s s/d %s).',
                $file->getClientOriginalName(),
                number_format($result['imported'], 0, ',', '.'),
                $result['machines'],
                $result['from'] ?? '-',
                $result['to'] ?? '-',
            ));
    }
}
