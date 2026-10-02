<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDcrImportRequest extends FormRequest
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
            'workbook' => ['required', 'file', 'extensions:xlsx', 'max:20480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'workbook.required' => 'Pilih file DCR Produksi (.xlsx) terlebih dahulu.',
            'workbook.extensions' => 'File harus berformat .xlsx.',
            'workbook.max' => 'Ukuran file maksimal 20 MB.',
        ];
    }
}
