<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommissionSettingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'basis' => ['required', 'string', Rule::in(['agent', 'product'])],
            'default_rate' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'basis.in' => 'Basis komisi harus agent atau product.',
            'default_rate.required' => 'Rate default wajib diisi.',
            'default_rate.numeric' => 'Rate default harus berupa angka.',
        ];
    }
}
