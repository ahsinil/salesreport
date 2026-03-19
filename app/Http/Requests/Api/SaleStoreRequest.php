<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SaleStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'distinct', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_name.required' => 'Nama pelanggan wajib diisi.',
            'items.required' => 'Item penjualan wajib diisi.',
            'items.min' => 'Tambahkan minimal satu item penjualan.',
            'items.*.product_id.required' => 'Produk pada item penjualan wajib dipilih.',
            'items.*.product_id.distinct' => 'Produk yang sama tidak boleh dipilih lebih dari sekali.',
            'items.*.qty.required' => 'Jumlah item wajib diisi.',
            'items.*.qty.integer' => 'Jumlah item harus berupa angka bulat.',
            'items.*.qty.min' => 'Jumlah item minimal 1.',
        ];
    }
}
