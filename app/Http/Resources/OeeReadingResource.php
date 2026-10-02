<?php

namespace App\Http\Resources;

use App\Models\OeeReading;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OeeReading
 */
class OeeReadingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'machine' => $this->whenLoaded('machine', fn () => $this->machine?->code),
            'recorded_at' => $this->recorded_at->toIso8601String(),
            'availability' => $this->availability,
            'performance' => $this->performance,
            'quality' => $this->quality,
            'oee' => $this->oee,
            'speed_ppm' => $this->speed_ppm,
            'total_count' => $this->total_count,
            'good_count' => $this->good_count,
            'reject_count' => $this->reject_count,
            'source' => $this->source,
        ];
    }
}
