<?php

use App\Models\Product;
use App\Services\CommissionService;
use App\Services\CurrencyFormatter;
use App\Support\Permissions;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $sortBy = 'id';
    public string $sortDirection = 'desc';
    public string $name = '';
    public string $price = '';
    public string $stock = '';
    public string $commissionRate = '';
    public ?int $editingId = null;
    public bool $showProductModal = false;

    public function mount(): void
    {
        $this->ensureCanViewProducts();
    }

    public function startCreating(): void
    {
        $this->ensureCanCreateProducts();

        $this->resetForm();
        $this->showProductModal = true;
    }

    public function save(): void
    {
        if ($this->editingId === null) {
            $this->ensureCanCreateProducts();
        } else {
            $this->ensureCanEditProducts();
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
        ];

        if (Auth::user()?->hasRole('admin')) {
            $rules['commissionRate'] = ['nullable', 'numeric', 'min:0'];
        }

        $validated = $this->validate($rules);

        $attributes = [
            'name' => $validated['name'],
            'price' => $validated['price'],
            'stock' => (int) $validated['stock'],
        ];

        if (Auth::user()?->hasRole('admin')) {
            $attributes['commission_rate'] = filled($validated['commissionRate'] ?? null) ? $validated['commissionRate'] : null;
        }

        Product::query()->updateOrCreate(
            ['id' => $this->editingId],
            $attributes,
        );

        session()->flash('success', $this->editingId === null ? 'Produk berhasil disimpan.' : 'Produk berhasil diperbarui.');

        $this->resetForm();
        $this->resetPage();
    }

    public function edit(int $productId): void
    {
        $this->ensureCanEditProducts();

        $product = Product::query()->findOrFail($productId);

        $this->editingId = $product->id;
        $this->name = $product->name;
        $this->price = (string) $product->price;
        $this->stock = (string) $product->stock;
        $this->commissionRate = $product->commission_rate !== null ? number_format((float) $product->commission_rate, 2, '.', '') : '';
        $this->resetValidation();
        $this->showProductModal = true;
    }

    public function delete(int $productId): void
    {
        $this->ensureCanDeleteProducts();

        $product = Product::query()->findOrFail($productId);

        if ($product->saleItems()->exists()) {
            session()->flash('error', 'Produk yang sudah tercatat dalam penjualan tidak dapat dihapus.');

            return;
        }

        $product->delete();

        session()->flash('success', 'Produk berhasil dihapus.');

        $this->resetPage();
    }

    public function resetForm(): void
    {
        $this->reset(['name', 'price', 'stock', 'commissionRate', 'editingId', 'showProductModal']);
        $this->resetValidation();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function sort(string $field): void
    {
        abort_unless(in_array($field, ['name', 'price', 'commission_rate', 'stock'], true), 404);

        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function with(): array
    {
        $this->ensureCanViewProducts();

        $commissionSettings = app(CommissionService::class)->settings();
        $defaultCommissionRate = (float) $commissionSettings->default_rate;
        $canCreateProducts = Auth::user()?->can(Permissions::CreateProducts) ?? false;
        $canEditProducts = Auth::user()?->can(Permissions::EditProducts) ?? false;
        $canDeleteProducts = Auth::user()?->can(Permissions::DeleteProducts) ?? false;
        $productsQuery = Product::query()
            ->when($this->search !== '', function ($query): void {
                $query->whereLike('name', '%'.$this->search.'%');
            });

        if ($this->sortBy === 'commission_rate') {
            $productsQuery
                ->orderByRaw('COALESCE(commission_rate, ?) '.$this->sortDirection, [$defaultCommissionRate])
                ->orderBy('id');
        } else {
            $productsQuery
                ->orderBy($this->sortBy, $this->sortDirection)
                ->when($this->sortBy !== 'id', function ($query): void {
                    $query->orderBy('id');
                });
        }

        return [
            'products' => $productsQuery->paginate(10),
            'canCreateProducts' => $canCreateProducts,
            'canEditProducts' => $canEditProducts,
            'canDeleteProducts' => $canDeleteProducts,
            'hasProductActions' => $canEditProducts || $canDeleteProducts,
            'canManageCommission' => Auth::user()?->hasRole('admin') ?? false,
            'commissionSettings' => $commissionSettings,
        ];
    }

    protected function ensureCanViewProducts(): void
    {
        abort_unless(Auth::user()?->can(Permissions::ViewProducts), 403);
    }

    protected function ensureCanCreateProducts(): void
    {
        abort_unless(Auth::user()?->can(Permissions::CreateProducts), 403);
    }

    protected function ensureCanEditProducts(): void
    {
        abort_unless(Auth::user()?->can(Permissions::EditProducts), 403);
    }

    protected function ensureCanDeleteProducts(): void
    {
        abort_unless(Auth::user()?->can(Permissions::DeleteProducts), 403);
    }
}; 

?>

<section class="flex flex-col gap-6">
    <div class="flex flex-col gap-2">
        <p class="text-sm font-medium uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Katalog</p>
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="text-3xl font-semibold text-zinc-950 dark:text-white">Manajemen produk</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    Kelola katalog produk yang digunakan pada alur entri penjualan. Jumlah stok akan diperbarui otomatis saat penjualan dicatat.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <div class="rounded-full border border-zinc-200 px-4 py-2 text-sm text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">
                    {{ $products->total() }} produk
                </div>
                @if ($canCreateProducts)
                    <flux:button variant="primary" type="button" wire:click="startCreating">
                        Produk baru
                    </flux:button>
                @endif
            </div>
        </div>
    </div>

    @include('partials.flash-messages')

    <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 pb-5 dark:border-zinc-800">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Daftar produk</h2>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Perbarui harga, kelola stok, dan pastikan daftar produk siap untuk transaksi.</p>
                </div>

                <div class="w-full lg:max-w-sm">
                    <flux:field>
                        <flux:label>Cari produk</flux:label>
                        <flux:input
                            wire:model.live.debounce.300ms="search"
                            name="search"
                            type="text"
                            placeholder="Cari nama produk"
                        />
                    </flux:field>
                </div>
            </div>
        </div>

        <div class="pt-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                    <thead>
                        <tr class="text-left text-zinc-500 dark:text-zinc-400">
                            <th class="pb-4 font-medium">
                                <button type="button" wire:click="sort('name')" class="group/sortable inline-flex items-center gap-2 transition hover:text-zinc-900 dark:hover:text-zinc-100">
                                    <span>Nama</span>
                                    <span class="text-zinc-400 group-hover/sortable:text-zinc-700 dark:group-hover/sortable:text-zinc-200">
                                        @if ($sortBy === 'name')
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
                                <button type="button" wire:click="sort('price')" class="group/sortable inline-flex items-center gap-2 transition hover:text-zinc-900 dark:hover:text-zinc-100">
                                    <span>Harga</span>
                                    <span class="text-zinc-400 group-hover/sortable:text-zinc-700 dark:group-hover/sortable:text-zinc-200">
                                        @if ($sortBy === 'price')
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
                            @if ($canManageCommission)
                                <th class="pb-4 font-medium">
                                    <button type="button" wire:click="sort('commission_rate')" class="group/sortable inline-flex items-center gap-2 transition hover:text-zinc-900 dark:hover:text-zinc-100">
                                        <span>Komisi</span>
                                        <span class="text-zinc-400 group-hover/sortable:text-zinc-700 dark:group-hover/sortable:text-zinc-200">
                                            @if ($sortBy === 'commission_rate')
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
                            @endif
                            <th class="pb-4 font-medium">
                                <button type="button" wire:click="sort('stock')" class="group/sortable inline-flex items-center gap-2 transition hover:text-zinc-900 dark:hover:text-zinc-100">
                                    <span>Stok</span>
                                    <span class="text-zinc-400 group-hover/sortable:text-zinc-700 dark:group-hover/sortable:text-zinc-200">
                                        @if ($sortBy === 'stock')
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
                            @if ($hasProductActions)
                                <th class="pb-4 text-right font-medium">Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($products as $product)
                            <tr wire:key="product-{{ $product->id }}">
                                <td class="py-4 pr-4 font-medium text-zinc-900 dark:text-zinc-100">{{ $product->name }}</td>
                                <td class="py-4 pr-4 text-zinc-600 dark:text-zinc-300">{{ CurrencyFormatter::rupiah($product->price) }}</td>
                                @if ($canManageCommission)
                                    <td class="py-4 pr-4 text-zinc-600 dark:text-zinc-300">
                                        @if ($product->commission_rate !== null)
                                            {{ number_format((float) $product->commission_rate, 2, ',', '.') }}%
                                        @else
                                            Default {{ number_format((float) $commissionSettings->default_rate, 2, ',', '.') }}%
                                        @endif
                                    </td>
                                @endif
                                <td class="py-4 pr-4 text-zinc-600 dark:text-zinc-300">{{ $product->stock }}</td>
                                @if ($hasProductActions)
                                    <td class="py-4 text-right">
                                        <div class="flex justify-end gap-2">
                                            @if ($canEditProducts)
                                                <button
                                                    type="button"
                                                    wire:click="edit({{ $product->id }})"
                                                    class="rounded-full border border-zinc-300 px-3 py-1.5 font-medium text-zinc-700 transition hover:border-zinc-400 hover:text-zinc-950 dark:border-zinc-700 dark:text-zinc-200 dark:hover:border-zinc-500 dark:hover:text-white"
                                                >
                                                    Ubah
                                                </button>
                                            @endif

                                            @if ($canDeleteProducts)
                                                <button
                                                    type="button"
                                                    wire:click="delete({{ $product->id }})"
                                                    class="rounded-full border border-rose-300 px-3 py-1.5 font-medium text-rose-700 transition hover:border-rose-400 hover:text-rose-900 dark:border-rose-500/30 dark:text-rose-300 dark:hover:border-rose-400 dark:hover:text-rose-200"
                                                >
                                                    Hapus
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 3 + ($canManageCommission ? 1 : 0) + ($hasProductActions ? 1 : 0) }}" class="py-10 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $search !== '' ? 'Tidak ada produk yang cocok dengan pencarian Anda.' : ($canCreateProducts ? 'Belum ada produk. Gunakan Produk baru untuk menambahkan produk pertama Anda.' : 'Belum ada produk yang dapat ditampilkan.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($products->hasPages())
                <div class="mt-6">
                    {{ $products->links() }}
                </div>
            @endif
        </div>
    </div>

    <flux:modal wire:model="showProductModal" focusable class="max-w-xl">
        <form wire:submit="save" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $editingId === null ? 'Tambah produk' : 'Ubah produk' }}</flux:heading>
                <p class="mt-2 text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                    Gunakan harga yang jelas dan pastikan jumlah stok akurat untuk alur penjualan.
                </p>
            </div>

            <flux:field>
                <flux:label>Nama produk</flux:label>
                <flux:input wire:model="name" name="name" type="text" placeholder="Produk A" />
                <flux:error name="name" />
            </flux:field>

            <div class="grid gap-5 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Harga</flux:label>
                    <flux:input wire:model="price" name="price" type="number" step="0.01" min="0" placeholder="25000" />
                    <flux:error name="price" />
                </flux:field>

                <flux:field>
                    <flux:label>Stok</flux:label>
                    <flux:input wire:model="stock" name="stock" type="number" min="0" step="1" placeholder="100" />
                    <flux:error name="stock" />
                </flux:field>
            </div>

            @if ($canManageCommission)
                <flux:field>
                    <flux:label>Komisi produk (%)</flux:label>
                    <flux:input wire:model="commissionRate" name="commissionRate" type="number" min="0" step="0.01" placeholder="Kosongkan untuk rate default" />
                    <flux:error name="commissionRate" />
                    <p class="mt-2 text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                        @if ($commissionSettings->basis === 'product')
                            Rate ini sedang aktif untuk perhitungan komisi produk. Kosongkan untuk memakai default {{ number_format((float) $commissionSettings->default_rate, 2, ',', '.') }}%.
                        @else
                            Rate ini disimpan untuk mode komisi berdasarkan produk. Saat ini default mode komisi masih berdasarkan agent.
                        @endif
                    </p>
                </flux:field>
            @endif

            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="resetForm">
                    Batal
                </flux:button>
                <flux:button variant="primary" type="submit">
                    {{ $editingId === null ? 'Simpan produk' : 'Perbarui produk' }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

</section>
