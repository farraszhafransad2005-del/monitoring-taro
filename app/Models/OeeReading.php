<?php

namespace App\Models;

use Database\Factories\OeeReadingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'machine_id', 'recorded_at', 'availability', 'performance', 'quality', 'oee',
    'speed_ppm', 'total_count', 'good_count', 'reject_count', 'source',
])]
class OeeReading extends Model
{
    /** @use HasFactory<OeeReadingFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'availability' => 'float',
            'performance' => 'float',
            'quality' => 'float',
            'oee' => 'float',
            'speed_ppm' => 'float',
            'total_count' => 'integer',
            'good_count' => 'integer',
            'reject_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Machine, $this>
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }
}
