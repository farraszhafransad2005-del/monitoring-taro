<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Machine;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOeeReadingRequest extends FormRequest
{
    /**
     * Only callers presenting the configured ingest token (e.g. Node-RED) may push readings.
     */
    public function authorize(): bool
    {
        $token = (string) config('oee.ingest_token');

        return $token !== '' && hash_equals($token, (string) $this->bearerToken());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'machine' => ['nullable', 'string', 'exists:machines,code'],
            'recorded_at' => ['nullable', 'date'],
            'availability' => ['required', 'numeric', 'between:0,100'],
            'performance' => ['required', 'numeric', 'between:0,100'],
            'quality' => ['required', 'numeric', 'between:0,100'],
            'oee' => ['nullable', 'numeric', 'between:0,100'],
            'speed_ppm' => ['nullable', 'numeric', 'min:0'],
            'total_count' => ['nullable', 'integer', 'min:0'],
            'good_count' => ['nullable', 'integer', 'min:0'],
            'reject_count' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('machine')) {
            $this->merge(['machine' => Machine::normalizeCode($this->string('machine'))]);
        }
    }
}
