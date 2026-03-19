<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportFilterRequest extends FormRequest
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
            'start_date' => ['required', 'date', 'before_or_equal:end_date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort_by' => ['required', 'string', Rule::in(['date', 'salesperson', 'customer_name', 'item_count', 'total_amount', 'commission_amount'])],
            'sort_direction' => ['required', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'start_date' => $this->input('start_date', now()->startOfMonth()->toDateString()),
            'end_date' => $this->input('end_date', now()->toDateString()),
            'sort_by' => $this->input('sort_by', 'date'),
            'sort_direction' => $this->input('sort_direction', 'desc'),
            'per_page' => (int) $this->input('per_page', 15),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'start_date.before_or_equal' => 'Tanggal mulai harus sebelum atau sama dengan tanggal akhir.',
            'end_date.after_or_equal' => 'Tanggal akhir harus sesudah atau sama dengan tanggal mulai.',
            'sort_by.in' => 'Kolom pengurutan laporan tidak valid.',
            'sort_direction.in' => 'Arah pengurutan harus asc atau desc.',
            'per_page.max' => 'Jumlah data per halaman maksimal 100.',
        ];
    }
}
