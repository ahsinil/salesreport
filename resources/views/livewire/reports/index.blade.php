<?php

use App\Exports\SalesExport;
use App\Models\Sale;
use App\Models\User;
use App\Services\CurrencyFormatter;
use App\Support\Permissions;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public string $startDate = '';
    public string $endDate = '';
    public string $search = '';
    public string $sortBy = 'date';
    public string $sortDirection = 'desc';

    public function mount(): void
    {
        abort_unless(Auth::user()?->can(Permissions::ViewReports), 403);

        $this->startDate = now()->startOfMonth()->toDateString();
        $this->endDate = now()->toDateString();
    }

    public function updatedStartDate(): void
    {
        $this->resetPage();
    }

    public function updatedEndDate(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function sort(string $field): void
    {
        abort_unless(in_array($field, ['date', 'salesperson', 'customer_name', 'items_sum_qty', 'total_amount', 'commission_amount'], true), 404);

        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = match ($field) {
                'date', 'items_sum_qty', 'total_amount', 'commission_amount' => 'desc',
                default => 'asc',
            };
        }

        $this->resetPage();
    }

    public function export(): mixed
    {
        $validated = $this->validatedDateRange();

        return Excel::download(
            new SalesExport($validated['startDate'], $validated['endDate']),
            'laporan-penjualan.xlsx',
        );
    }

    public function chartSeries(): array
    {
        $validated = $this->validatedDateRange();

        return Sale::query()
            ->selectRaw('date, SUM(total_amount) as total')
            ->whereBetween('date', [$validated['startDate'], $validated['endDate']])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($sale): array => [
                'day' => Carbon::parse($sale->date)->translatedFormat('j M'),
                'total' => round((float) $sale->total, 2),
            ])
            ->values()
            ->all();
    }

    public function with(): array
    {
        $validated = $this->validatedDateRange();

        $sales = $this->salesQuery($validated['startDate'], $validated['endDate'])->paginate(15);
        $chartData = $this->chartSeries();

        return [
            'sales' => $sales,
            'chartData' => $chartData,
            'chartMaxTotal' => count($chartData) > 0 ? max(array_column($chartData, 'total')) : 0,
            'totalSales' => Sale::query()->whereBetween('date', [$validated['startDate'], $validated['endDate']])->sum('total_amount'),
            'totalCommission' => Sale::query()->whereBetween('date', [$validated['startDate'], $validated['endDate']])->sum('commission_amount'),
            'transactionCount' => Sale::query()->whereBetween('date', [$validated['startDate'], $validated['endDate']])->count(),
        ];
    }

    protected function salesQuery(string $startDate, string $endDate): Builder
    {
        $query = Sale::query()
            ->with(['items.product', 'user'])
            ->withSum('items', 'qty')
            ->whereBetween('date', [$startDate, $endDate])
            ->when($this->search !== '', function (Builder $query): void {
                $searchTerm = '%'.$this->search.'%';

                $query->where(function (Builder $searchQuery) use ($searchTerm): void {
                    $searchQuery
                        ->whereLike('customer_name', $searchTerm)
                        ->orWhereHas('user', function (Builder $userQuery) use ($searchTerm): void {
                            $userQuery->whereLike('name', $searchTerm);
                        });
                });
            });

        if ($this->sortBy === 'salesperson') {
            $query->orderBy(
                User::query()
                    ->select('name')
                    ->whereColumn('users.id', 'sales.user_id'),
                $this->sortDirection,
            );
        } else {
            $query->orderBy($this->sortBy, $this->sortDirection);
        }

        if ($this->sortBy !== 'id') {
            $query->orderBy('id', 'desc');
        }

        return $query;
    }

    protected function validatedDateRange(): array
    {
        return $this->validate([
            'startDate' => ['required', 'date', 'before_or_equal:endDate'],
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
        ]);
    }
}; ?>

<section class="flex flex-col gap-6">
    <div class="flex flex-col gap-2">
        <p class="text-sm font-medium uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Pelaporan</p>
        <h1 class="text-3xl font-semibold text-zinc-950 dark:text-white">Laporan penjualan</h1>
        <p class="max-w-2xl text-sm leading-6 text-zinc-600 dark:text-zinc-300">
            Filter transaksi berdasarkan rentang tanggal, tinjau performa harian, dan ekspor data saat ini ke Excel.
        </p>
    </div>

    @include('partials.flash-messages')

    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Pendapatan</p>
            <p class="mt-3 text-3xl font-semibold text-zinc-950 dark:text-white">{{ CurrencyFormatter::rupiah($totalSales) }}</p>
        </div>
        <div class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Komisi</p>
            <p class="mt-3 text-3xl font-semibold text-zinc-950 dark:text-white">{{ CurrencyFormatter::rupiah($totalCommission) }}</p>
        </div>
        <div class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Transaksi</p>
            <p class="mt-3 text-3xl font-semibold text-zinc-950 dark:text-white">{{ $transactionCount }}</p>
        </div>
    </div>

    <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Tanggal mulai</flux:label>
                    <flux:input wire:model.live="startDate" name="startDate" type="date" />
                    <flux:error name="startDate" />
                </flux:field>

                <flux:field>
                    <flux:label>Tanggal akhir</flux:label>
                    <flux:input wire:model.live="endDate" name="endDate" type="date" />
                    <flux:error name="endDate" />
                </flux:field>
            </div>

            <flux:button variant="primary" type="button" wire:click="export">
                Ekspor ke Excel
            </flux:button>
        </div>
    </div>

    <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Grafik penjualan harian</h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Grafik batang total penjualan untuk rentang tanggal yang dipilih.</p>
            </div>
        </div>

        @if ($chartData === [])
            <div class="mt-6 flex h-80 items-center justify-center rounded-2xl border border-dashed border-zinc-200 bg-zinc-50 text-sm text-zinc-500 dark:border-zinc-800 dark:bg-zinc-950/40 dark:text-zinc-400">
                Belum ada penjualan pada rentang tanggal ini.
            </div>
        @else
            <div class="mt-6 overflow-x-auto pb-2">
                <div class="flex h-80 min-w-max items-end gap-3 rounded-2xl border border-zinc-200/80 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-950/50">
                    @foreach ($chartData as $point)
                        @php
                            $barHeight = $chartMaxTotal > 0 ? max(($point['total'] / $chartMaxTotal) * 100, 12) : 0;
                        @endphp

                        <div class="flex w-16 min-w-16 flex-col justify-end gap-3 sm:w-20 sm:min-w-20">
                            <p class="text-center text-[11px] font-medium text-zinc-500 dark:text-zinc-400">
                                {{ CurrencyFormatter::rupiah($point['total']) }}
                            </p>

                            <div class="flex h-56 items-end">
                                <div
                                    class="w-full rounded-t-3xl bg-gradient-to-t from-emerald-600 to-emerald-400 shadow-[0_18px_40px_-24px_rgba(5,150,105,0.8)]"
                                    style="height: {{ number_format($barHeight, 2, '.', '') }}%"
                                ></div>
                            </div>

                            <p class="text-center text-xs font-medium text-zinc-600 dark:text-zinc-300">
                                {{ $point['day'] }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="mb-6 flex flex-col gap-4 border-b border-zinc-200 pb-5 dark:border-zinc-800 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Daftar penjualan</h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Gunakan pencarian untuk menyaring pelanggan atau nama penjual dalam rentang tanggal aktif.</p>
            </div>

            <div class="w-full lg:max-w-sm">
                <flux:field>
                    <flux:label>Cari laporan penjualan</flux:label>
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        name="search"
                        type="text"
                        placeholder="Cari pelanggan atau penjual"
                    />
                </flux:field>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                <thead>
                    <tr class="text-left text-zinc-500 dark:text-zinc-400">
                        <th class="pb-4 font-medium">
                            <button type="button" wire:click="sort('date')" class="group/sortable inline-flex items-center gap-2 transition hover:text-zinc-900 dark:hover:text-zinc-100">
                                <span>Tanggal</span>
                                <span class="text-zinc-400 group-hover/sortable:text-zinc-700 dark:group-hover/sortable:text-zinc-200">
                                    @if ($sortBy === 'date')
                                        @if ($sortDirection === 'asc')
                                            <flux:icon.chevron-up variant="micro" />
                                        @else
                                            <flux:icon.chevron-down variant="micro" />
                                        @endif
                                    @else
                                        <flux:icon.chevron-down variant="micro" class="opacity-50" />
                                    @endif
                                </span>
                            </button>
                        </th>
                        <th class="pb-4 font-medium">
                            <button type="button" wire:click="sort('salesperson')" class="group/sortable inline-flex items-center gap-2 transition hover:text-zinc-900 dark:hover:text-zinc-100">
                                <span>Penjual</span>
                                <span class="text-zinc-400 group-hover/sortable:text-zinc-700 dark:group-hover/sortable:text-zinc-200">
                                    @if ($sortBy === 'salesperson')
                                        @if ($sortDirection === 'asc')
                                            <flux:icon.chevron-up variant="micro" />
                                        @else
                                            <flux:icon.chevron-down variant="micro" />
                                        @endif
                                    @else
                                        <flux:icon.chevron-down variant="micro" class="opacity-50" />
                                    @endif
                                </span>
                            </button>
                        </th>
                        <th class="pb-4 font-medium">
                            <button type="button" wire:click="sort('customer_name')" class="group/sortable inline-flex items-center gap-2 transition hover:text-zinc-900 dark:hover:text-zinc-100">
                                <span>Pelanggan</span>
                                <span class="text-zinc-400 group-hover/sortable:text-zinc-700 dark:group-hover/sortable:text-zinc-200">
                                    @if ($sortBy === 'customer_name')
                                        @if ($sortDirection === 'asc')
                                            <flux:icon.chevron-up variant="micro" />
                                        @else
                                            <flux:icon.chevron-down variant="micro" />
                                        @endif
                                    @else
                                        <flux:icon.chevron-down variant="micro" class="opacity-50" />
                                    @endif
                                </span>
                            </button>
                        </th>
                        <th class="pb-4 font-medium">
                            <button type="button" wire:click="sort('items_sum_qty')" class="group/sortable inline-flex items-center gap-2 transition hover:text-zinc-900 dark:hover:text-zinc-100">
                                <span>Item</span>
                                <span class="text-zinc-400 group-hover/sortable:text-zinc-700 dark:group-hover/sortable:text-zinc-200">
                                    @if ($sortBy === 'items_sum_qty')
                                        @if ($sortDirection === 'asc')
                                            <flux:icon.chevron-up variant="micro" />
                                        @else
                                            <flux:icon.chevron-down variant="micro" />
                                        @endif
                                    @else
                                        <flux:icon.chevron-down variant="micro" class="opacity-50" />
                                    @endif
                                </span>
                            </button>
                        </th>
                        <th class="pb-4 font-medium">
                            <button type="button" wire:click="sort('total_amount')" class="group/sortable inline-flex items-center gap-2 transition hover:text-zinc-900 dark:hover:text-zinc-100">
                                <span>Total</span>
                                <span class="text-zinc-400 group-hover/sortable:text-zinc-700 dark:group-hover/sortable:text-zinc-200">
                                    @if ($sortBy === 'total_amount')
                                        @if ($sortDirection === 'asc')
                                            <flux:icon.chevron-up variant="micro" />
                                        @else
                                            <flux:icon.chevron-down variant="micro" />
                                        @endif
                                    @else
                                        <flux:icon.chevron-down variant="micro" class="opacity-50" />
                                    @endif
                                </span>
                            </button>
                        </th>
                        <th class="pb-4 font-medium">
                            <button type="button" wire:click="sort('commission_amount')" class="group/sortable inline-flex items-center gap-2 transition hover:text-zinc-900 dark:hover:text-zinc-100">
                                <span>Komisi</span>
                                <span class="text-zinc-400 group-hover/sortable:text-zinc-700 dark:group-hover/sortable:text-zinc-200">
                                    @if ($sortBy === 'commission_amount')
                                        @if ($sortDirection === 'asc')
                                            <flux:icon.chevron-up variant="micro" />
                                        @else
                                            <flux:icon.chevron-down variant="micro" />
                                        @endif
                                    @else
                                        <flux:icon.chevron-down variant="micro" class="opacity-50" />
                                    @endif
                                </span>
                            </button>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($sales as $sale)
                        <tr wire:key="sale-report-{{ $sale->id }}">
                            <td class="py-4 pr-4 text-zinc-600 dark:text-zinc-300">{{ $sale->date->translatedFormat('j M Y') }}</td>
                            <td class="py-4 pr-4 text-zinc-600 dark:text-zinc-300">{{ $sale->user->name }}</td>
                            <td class="py-4 pr-4 font-medium text-zinc-900 dark:text-zinc-100">{{ $sale->customer_name }}</td>
                            <td class="py-4 pr-4 text-zinc-600 dark:text-zinc-300">{{ (int) ($sale->items_sum_qty ?? $sale->items->sum('qty')) }}</td>
                            <td class="py-4 pr-4 text-zinc-600 dark:text-zinc-300">{{ CurrencyFormatter::rupiah($sale->total_amount) }}</td>
                            <td class="py-4 text-zinc-600 dark:text-zinc-300">{{ CurrencyFormatter::rupiah($sale->commission_amount) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-10 text-center text-sm text-zinc-500 dark:text-zinc-400">{{ $search !== '' ? 'Tidak ada penjualan yang cocok dengan filter pencarian ini.' : 'Tidak ada penjualan yang cocok dengan rentang tanggal yang dipilih.' }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($sales->hasPages())
            <div class="mt-6">
                {{ $sales->links() }}
            </div>
        @endif
    </div>
</section>
