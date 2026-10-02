<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DashboardFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'shift' => ['nullable', 'integer', 'in:1,2,3'],
            'tab' => ['nullable', 'in:overview,packaging'],
        ];
    }

    public function shift(): ?int
    {
        return $this->filled('shift') ? $this->integer('shift') : null;
    }
}
