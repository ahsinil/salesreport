<?php

use App\Models\Sale;
use App\Services\CurrencyFormatter;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public string $search = '';

    public function mount(): void
    {
        $this->ensureCanViewSalesList();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $this->ensureCanViewSalesList();

        $sales = Sale::query()
            ->with(['items.product', 'user'])
            ->withSum('items', 'qty')
            ->when($this->search !== '', function (Builder $query): void {
                $searchTerm = '%'.$this->search.'%';

                $query->where(function (Builder $searchQuery) use ($searchTerm): void {
                    $searchQuery
                        ->whereLike('customer_name', $searchTerm)
                        ->orWhereHas('user', function (Builder $userQuery) use ($searchTerm): void {
                            $userQuery->whereLike('name', $searchTerm);
                        })
                        ->orWhereHas('items.product', function (Builder $productQuery) use ($searchTerm): void {
                            $productQuery->whereLike('name', $searchTerm);
                        });
                });
            })
            ->latest('date')
            ->latest('id')
            ->paginate(10);

        return [
            'sales' => $sales,
        ];
    }

    protected function ensureCanViewSalesList(): void
    {
        abort_unless(Auth::user()?->can(Permissions::ViewSalesList), 403);
    }
}; ?>

<section class="flex flex-col gap-6">
    <div class="flex flex-col gap-2">
        <p class="text-sm font-medium uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Transaksi</p>
        <h1 class="text-3xl font-semibold text-zinc-950 dark:text-white">Daftar penjualan</h1>
        <p class="max-w-2xl text-sm leading-6 text-zinc-600 dark:text-zinc-300">
            Lihat semua transaksi yang sudah dicatat, telusuri pelanggan atau nama sales, lalu buka rincian item pada setiap penjualan.
        </p>
    </div>

    <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Cari transaksi</h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Saring berdasarkan pelanggan, sales, atau nama produk yang ada di item penjualan.</p>
            </div>

            <div class="w-full lg:max-w-sm">
                <flux:field>
                    <flux:label>Cari daftar penjualan</flux:label>
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        name="search"
                        type="text"
                        placeholder="Cari pelanggan, sales, atau produk"
                    />
                </flux:field>
            </div>
        </div>
    </div>

    @if ($sales->count() === 0)
        <div class="rounded-3xl border border-dashed border-zinc-300 bg-white px-6 py-12 text-center dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-base font-medium text-zinc-950 dark:text-white">Belum ada penjualan yang cocok.</p>
            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Ubah kata kunci pencarian atau tambahkan transaksi baru untuk mulai mengisi daftar ini.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($sales as $sale)
                <article class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                        <div class="min-w-0 space-y-4">
                            <div class="space-y-2">
                                <p class="text-sm font-medium uppercase tracking-[0.18em] text-zinc-500 dark:text-zinc-400">
                                    Transaksi #{{ str_pad((string) $sale->id, 5, '0', STR_PAD_LEFT) }}
                                </p>
                                <h2 class="text-2xl font-semibold text-zinc-950 dark:text-white">{{ $sale->customer_name }}</h2>
                            </div>

                            <div class="flex flex-wrap gap-3 text-sm text-zinc-500 dark:text-zinc-400">
                                <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 dark:border-zinc-800 dark:bg-zinc-950">
                                    Tanggal {{ $sale->date?->translatedFormat('j M Y') }}
                                </span>
                                <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 dark:border-zinc-800 dark:bg-zinc-950">
                                    Sales {{ $sale->user?->name ?? 'Tidak diketahui' }}
                                </span>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3 xl:min-w-[22rem]">
                            <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/70">
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">Total</p>
                                <p class="mt-2 text-lg font-semibold text-zinc-950 dark:text-white">{{ CurrencyFormatter::rupiah($sale->total_amount) }}</p>
                            </div>
                            <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/70">
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">Komisi</p>
                                <p class="mt-2 text-lg font-semibold text-zinc-950 dark:text-white">{{ CurrencyFormatter::rupiah($sale->commission_amount) }}</p>
                            </div>
                            <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/70">
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">Total item</p>
                                <p class="mt-2 text-lg font-semibold text-zinc-950 dark:text-white">{{ number_format((float) ($sale->items_sum_qty ?? 0)) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                            <thead>
                                <tr class="text-left text-zinc-500 dark:text-zinc-400">
                                    <th class="pb-4 font-medium">Produk</th>
                                    <th class="pb-4 font-medium">Jumlah</th>
                                    <th class="pb-4 font-medium">Harga</th>
                                    <th class="pb-4 font-medium">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach ($sale->items as $item)
                                    <tr class="align-top">
                                        <td class="py-4 pr-4">
                                            <p class="font-medium text-zinc-950 dark:text-white">{{ $item->product?->name ?? 'Produk tidak ditemukan' }}</p>
                                        </td>
                                        <td class="py-4 pr-4 text-zinc-600 dark:text-zinc-300">{{ number_format($item->qty) }}</td>
                                        <td class="py-4 pr-4 text-zinc-600 dark:text-zinc-300">{{ CurrencyFormatter::rupiah($item->price) }}</td>
                                        <td class="py-4 text-zinc-950 dark:text-white">{{ CurrencyFormatter::rupiah($item->subtotal) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </article>
            @endforeach
        </div>

        <div>
            {{ $sales->links() }}
        </div>
    @endif
</section>
