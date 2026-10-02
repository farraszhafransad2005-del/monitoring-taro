<?php

namespace App\Console\Commands;

use App\Actions\ImportDcrWorkbook;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('oee:import-dcr {path? : File .xlsx DCR Produksi; default semua file di storage/app/imports}')]
#[Description('Import data produksi dari workbook DCR Produksi (sheet Packing)')]
class ImportDcrCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ImportDcrWorkbook $importer): int
    {
        $paths = $this->argument('path')
            ? [$this->argument('path')]
            : glob(config('oee.imports_path').DIRECTORY_SEPARATOR.'*.xlsx');

        if ($paths === [] || $paths === false) {
            $this->components->warn('Tidak ada file .xlsx di '.config('oee.imports_path'));

            return self::SUCCESS;
        }

        foreach ($paths as $path) {
            try {
                $result = $importer->handle($path);
            } catch (InvalidArgumentException $exception) {
                $this->components->error(basename($path).': '.$exception->getMessage());

                return self::FAILURE;
            }

            $this->components->info(sprintf(
                '%s: %d baris diimpor (%s s/d %s), %d mesin, %d baris dilewati.',
                basename($path), $result['imported'], $result['from'] ?? '-', $result['to'] ?? '-', $result['machines'], $result['skipped'],
            ));
        }

        return self::SUCCESS;
    }
}
