<?php

use App\Models\Product;
use App\Services\CommissionService;
use App\Services\CurrencyFormatter;
use App\Services\SaleService;
use App\Support\Permissions;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public string $customerName = '';

    /**
     * @var array<int, array{product_id:string, qty:string}>
     */
    public array $items = [];

    public function mount(): void
    {
        $this->ensureCanCreateSales();

        if ($this->items === []) {
            $this->addItem();
        }
    }

    public function addItem(): void
    {
        $this->ensureCanCreateSales();

        $this->items[] = [
            'product_id' => '',
            'qty' => '1',
        ];
    }

    public function removeItem(int $index): void
    {
        $this->ensureCanCreateSales();

        if (count($this->items) === 1) {
            $this->items = [[
                'product_id' => '',
                'qty' => '1',
            ]];

            return;
        }

        unset($this->items[$index]);

        $this->items = array_values($this->items);
    }

    public function save(): void
    {
        $this->ensureCanCreateSales();

        $validated = $this->validate([
            'customerName' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'distinct', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ]);

        app(SaleService::class)->createSale(
            Auth::user(),
            $validated['customerName'],
            $validated['items'],
        );

        session()->flash('success', 'Penjualan berhasil dicatat.');

        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->reset(['customerName', 'items']);
        $this->items = [[
            'product_id' => '',
            'qty' => '1',
        ]];
        $this->resetValidation();
    }

    public function with(): array
    {
        $this->ensureCanCreateSales();

        $products = Product::query()->orderBy('name')->get();
        $productsById = $products->keyBy('id');
        $commissionService = app(CommissionService::class);
        $commissionSettings = $commissionService->settings();
        $commissionLineItems = collect($this->items)->map(function (array $item) use ($productsById): ?array {
            $product = $productsById->get((int) ($item['product_id'] ?? 0));

            if ($product === null) {
                return null;
            }

            return [
                'product' => $product,
                'subtotal' => round((float) $product->price * (int) ($item['qty'] ?: 0), 2),
            ];
        })->filter()->values();

        $estimatedTotal = round((float) $commissionLineItems->sum('subtotal'), 2);
        $estimatedCommission = $commissionService->calculate(Auth::user(), $commissionLineItems->all());

        if ($commissionSettings->basis === 'product') {
            $commissionHelperText = 'Komisi dihitung per produk. Produk tanpa rate khusus akan memakai rate default '.number_format((float) $commissionSettings->default_rate, 2, ',', '.').'%.'; 
        } else {
            $commissionHelperText = 'Komisi sales ini memakai rate '.number_format($commissionService->resolveAgentRate(Auth::user(), $commissionSettings), 2, ',', '.').'% dari total transaksi.';
        }

        return [
            'products' => $products,
            'estimatedTotal' => $estimatedTotal,
            'estimatedCommission' => $estimatedCommission,
            'commissionBasisLabel' => $commissionSettings->basis === 'product' ? 'Basis produk' : 'Basis agent',
            'commissionHelperText' => $commissionHelperText,
        ];
    }

    protected function ensureCanCreateSales(): void
    {
        abort_unless(Auth::user()?->can(Permissions::CreateSales), 403);
    }
}; ?>

<section class="flex flex-col gap-6">
    <div class="flex flex-col gap-2">
        <p class="text-sm font-medium uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Transaksi</p>
        <h1 class="text-3xl font-semibold text-zinc-950 dark:text-white">Entri penjualan</h1>
        <p class="max-w-2xl text-sm leading-6 text-zinc-600 dark:text-zinc-300">
            Buat penjualan multi-item, validasi stok sebelum disimpan, dan biarkan service menghitung total serta komisi secara otomatis.
        </p>
    </div>

    @include('partials.flash-messages')

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(0,0.9fr)]">
        <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <form wire:submit="save" class="space-y-6">
                <flux:field>
                    <flux:label>Nama pelanggan</flux:label>
                    <flux:input wire:model="customerName" name="customerName" type="text" placeholder="Pelanggan umum" />
                    <flux:error name="customerName" />
                </flux:field>

                <div class="space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Item penjualan</h2>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">Setiap produk hanya dapat muncul sekali dalam satu transaksi.</p>
                        </div>
                        <button
                            type="button"
                            wire:click="addItem"
                            class="rounded-full border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 transition hover:border-zinc-400 hover:text-zinc-950 dark:border-zinc-700 dark:text-zinc-200 dark:hover:border-zinc-500 dark:hover:text-white"
                        >
                            Tambah item
                        </button>
                    </div>

                    @error('items')
                        <p class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200">
                            {{ $message }}
                        </p>
                    @enderror

                    <div class="space-y-4">
                        @foreach ($items as $index => $item)
                            @php($selectedProduct = $products->firstWhere('id', (int) ($item['product_id'] ?? 0)))
                            <div wire:key="sale-item-{{ $index }}" class="rounded-3xl border border-zinc-200 bg-zinc-50/80 p-5 dark:border-zinc-800 dark:bg-zinc-800/70">
                                <div class="grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,0.7fr)_auto]">
                                    <flux:field>
                                        <flux:label>Produk</flux:label>
                                        <flux:select wire:model.live="items.{{ $index }}.product_id" name="items.{{ $index }}.product_id">
                                            <option value="">Pilih produk</option>
                                            @foreach ($products as $product)
                                                <option value="{{ $product->id }}">
                                                    {{ $product->name }} ({{ CurrencyFormatter::rupiah($product->price) }})
                                                </option>
                                            @endforeach
                                        </flux:select>
                                        <flux:error name="items.{{ $index }}.product_id" />
                                    </flux:field>

                                    <flux:field>
                                        <flux:label>Jumlah</flux:label>
                                        <flux:input wire:model.live.debounce.150ms="items.{{ $index }}.qty" name="items.{{ $index }}.qty" type="number" min="1" step="1" />
                                        <flux:error name="items.{{ $index }}.qty" />
                                    </flux:field>

                                    <div class="flex items-end">
                                        <button
                                            type="button"
                                            wire:click="removeItem({{ $index }})"
                                            class="w-full rounded-full border border-rose-300 px-4 py-2.5 text-sm font-medium text-rose-700 transition hover:border-rose-400 hover:text-rose-900 dark:border-rose-500/30 dark:text-rose-300 dark:hover:border-rose-400 dark:hover:text-rose-200"
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-4 flex flex-wrap gap-3 text-sm text-zinc-500 dark:text-zinc-400">
                                    @if ($selectedProduct !== null)
                                        <span>Stok tersedia: {{ $selectedProduct->stock }}</span>
                                        <span>Harga satuan: {{ CurrencyFormatter::rupiah($selectedProduct->price) }}</span>
                                        <span>
                                            Subtotal baris:
                                            {{ CurrencyFormatter::rupiah(round((float) $selectedProduct->price * (int) ($item['qty'] ?: 0), 2)) }}
                                        </span>
                                    @else
                                        <span>Pilih produk untuk melihat stok dan harga.</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <flux:button variant="primary" type="submit" class="justify-center sm:flex-1">
                        Simpan penjualan
                    </flux:button>
                    <button
                        type="button"
                        wire:click="resetForm"
                        class="inline-flex items-center justify-center rounded-full border border-zinc-300 px-4 py-2.5 text-sm font-medium text-zinc-700 transition hover:border-zinc-400 hover:text-zinc-950 dark:border-zinc-700 dark:text-zinc-200 dark:hover:border-zinc-500 dark:hover:text-white sm:flex-1"
                    >
                        Atur ulang form
                    </button>
                </div>
            </form>
        </div>

        <div class="grid gap-4">
            <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Estimasi total</p>
                <p class="mt-3 text-3xl font-semibold text-zinc-950 dark:text-white">{{ CurrencyFormatter::rupiah($estimatedTotal) }}</p>
                <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Nilai ini mengikuti pilihan produk dan jumlah item yang sedang diisi.</p>
            </div>

            <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-6 shadow-sm dark:border-emerald-500/30 dark:bg-emerald-500/10">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm text-emerald-700 dark:text-emerald-300">Estimasi komisi</p>
                    <span class="rounded-full bg-white/80 px-3 py-1 text-xs font-medium text-emerald-700 dark:bg-zinc-950/60 dark:text-emerald-300">
                        {{ $commissionBasisLabel }}
                    </span>
                </div>
                <p class="mt-3 text-3xl font-semibold text-zinc-950 dark:text-white">{{ CurrencyFormatter::rupiah($estimatedCommission) }}</p>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $commissionHelperText }}</p>
            </div>

            <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Catatan alur kerja</h2>
                <ul class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    <li>Stok dikunci dan dikurangi di dalam transaksi database.</li>
                    <li>Produk duplikat diblokir untuk mencegah perhitungan ganda yang tidak sengaja.</li>
                    <li>Produk dengan stok menipis tetap terlihat di dasbor untuk tindak lanjut.</li>
                </ul>
            </div>
        </div>
    </div>
</section>
