<?php

namespace App\Models;

use App\Support\OeeMetrics;
use Database\Factories\ProductionRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['id'])]
class ProductionRecord extends Model
{
    /** @use HasFactory<ProductionRecordFactory> */
    use HasFactory;

    /**
     * Label etiket reject columns, keyed by column name.
     *
     * @var array<string, string>
     */
    public const REJECT_CATEGORIES = [
        'reject_setup' => 'Setting Awal',
        'reject_roll_change' => 'Ganti Roll & Selendang',
        'reject_post_repair_check' => 'Cek Kosongan After Perbaikan Mesin',
        'reject_empty_pack' => 'Pack Kosong',
        'reject_bad_coding' => 'Coding Tidak Standar',
        'reject_leaking_pack' => 'Pack Bocor',
        'reject_trapped_product' => 'Kue Terjepit',
        'reject_overlap' => 'Overlap',
        'reject_emark' => 'E-Mark',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'production_date' => 'date',
            'shift' => 'integer',
            'speed_ppm' => 'integer',
            'pcs_per_carton' => 'integer',
            'kg_per_carton' => 'float',
            'planned_minutes' => 'float',
            'unavailable_minutes' => 'float',
            'downtime_minutes' => 'float',
            'target_cartons' => 'float',
            'actual_cartons' => 'integer',
            'actual_kg' => 'float',
            'cake_usage_kg' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Machine, $this>
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * Only records with enough data (ideal speed, pack size, available time) to compute OEE.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function measurable(Builder $query): void
    {
        $query->where('speed_ppm', '>', 0)
            ->where('pcs_per_carton', '>', 0)
            ->whereRaw('planned_minutes - unavailable_minutes > 0');
    }

    /**
     * SQL aggregate expressions consumed by {@see OeeMetrics::fromAggregate()}.
     */
    public static function oeeAggregateSelect(): string
    {
        return implode(', ', [
            'SUM(planned_minutes - unavailable_minutes) as available_minutes',
            'SUM(downtime_minutes) as downtime_minutes',
            'SUM((planned_minutes - unavailable_minutes - downtime_minutes) * speed_ppm) as ideal_pcs',
            'SUM(actual_cartons * pcs_per_carton) as actual_pcs',
            'SUM(reject_total_pcs) as reject_pcs',
            'SUM(actual_kg) as good_kg',
            'COUNT(*) as record_count',
        ]);
    }
}
