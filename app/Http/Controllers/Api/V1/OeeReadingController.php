<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreOeeReadingRequest;
use App\Http\Resources\OeeReadingResource;
use App\Models\Machine;
use App\Models\OeeReading;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OeeReadingController extends Controller
{
    /**
     * Latest live readings in chronological order, optionally for one machine.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'machine' => ['nullable', 'string', 'max:10'],
            'limit' => ['nullable', 'integer', 'between:1,200'],
        ]);

        $readings = OeeReading::query()
            ->with('machine')
            ->when($validated['machine'] ?? null, fn (Builder $query, string $code) => $query->whereRelation('machine', 'code', Machine::normalizeCode($code)))
            ->latest('recorded_at')
            ->latest('id')
            ->limit($validated['limit'] ?? 20)
            ->get()
            ->reverse()
            ->values();

        return OeeReadingResource::collection($readings);
    }

    public function store(StoreOeeReadingRequest $request): OeeReadingResource
    {
        $validated = $request->validated();

        $reading = OeeReading::create([
            'machine_id' => isset($validated['machine']) ? Machine::where('code', $validated['machine'])->value('id') : null,
            'recorded_at' => $validated['recorded_at'] ?? now(),
            'availability' => $validated['availability'],
            'performance' => $validated['performance'],
            'quality' => $validated['quality'],
            'oee' => $validated['oee'] ?? round($validated['availability'] * $validated['performance'] * $validated['quality'] / 10000, 2),
            'speed_ppm' => $validated['speed_ppm'] ?? null,
            'total_count' => $validated['total_count'] ?? null,
            'good_count' => $validated['good_count'] ?? null,
            'reject_count' => $validated['reject_count'] ?? null,
            'source' => 'api',
        ]);

        return new OeeReadingResource($reading->load('machine'));
    }
}
