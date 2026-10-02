<?php

namespace App\Models;

use Database\Factories\MachineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'type', 'production_house'])]
class Machine extends Model
{
    /** @use HasFactory<MachineFactory> */
    use HasFactory;

    /**
     * @return HasMany<ProductionRecord, $this>
     */
    public function productionRecords(): HasMany
    {
        return $this->hasMany(ProductionRecord::class);
    }

    /**
     * @return HasMany<OeeReading, $this>
     */
    public function oeeReadings(): HasMany
    {
        return $this->hasMany(OeeReading::class);
    }

    /**
     * Normalize a machine number from the DCR ("1", 1, "01") into its two-digit code.
     */
    public static function normalizeCode(int|string $code): string
    {
        return str_pad(trim((string) $code), 2, '0', STR_PAD_LEFT);
    }
}
