<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleIndexRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:255'],
            'sort_by' => ['required', 'string', Rule::in(['date', 'salesperson', 'customer_name', 'total_amount', 'commission_amount', 'item_count'])],
            'sort_direction' => ['required', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sort_by' => $this->input('sort_by', 'date'),
            'sort_direction' => $this->input('sort_direction', 'desc'),
            'per_page' => (int) $this->input('per_page', 10),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sort_by.in' => 'Kolom pengurutan penjualan tidak valid.',
            'sort_direction.in' => 'Arah pengurutan harus asc atau desc.',
            'per_page.max' => 'Jumlah data per halaman maksimal 100.',
        ];
    }
}
