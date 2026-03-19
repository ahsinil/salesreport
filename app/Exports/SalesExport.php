<?php

namespace App\Exports;

use App\Models\Sale;
use App\Services\CurrencyFormatter;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SalesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        public string $startDate,
        public string $endDate,
    ) {
    }

    public function query(): Builder
    {
        return Sale::query()
            ->with('user')
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->latest('date')
            ->latest('id');
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['ID', 'Tanggal', 'Penjual', 'Pelanggan', 'Total', 'Komisi'];
    }

    /**
     * @return list<int|string>
     */
    public function map($sale): array
    {
        return [
            $sale->id,
            $sale->date->format('Y-m-d'),
            $sale->user->name,
            $sale->customer_name,
            CurrencyFormatter::rupiah($sale->total_amount),
            CurrencyFormatter::rupiah($sale->commission_amount),
        ];
    }
}
