<?php

use App\Models\CommissionSetting;
use App\Models\User;
use App\Services\CommissionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $sortBy = 'id';
    public string $sortDirection = 'desc';
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $commissionRate = '';
    public string $commissionBasis = 'agent';
    public string $defaultCommissionRate = '5.00';
    public ?int $editingId = null;
    public bool $showSalesAccountModal = false;

    public function mount(): void
    {
        $this->ensureAdmin();
        $this->fillCommissionSettings();
    }

    public function startCreating(): void
    {
        $this->ensureAdmin();

        $this->resetForm();
        $this->showSalesAccountModal = true;
    }

    public function edit(int $userId): void
    {
        $this->ensureAdmin();

        $salesUser = User::query()
            ->role('sales')
            ->findOrFail($userId);

        $this->editingId = $salesUser->id;
        $this->name = $salesUser->name;
        $this->email = $salesUser->email;
        $this->password = '';
        $this->password_confirmation = '';
        $this->commissionRate = $salesUser->commission_rate !== null ? number_format((float) $salesUser->commission_rate, 2, '.', '') : '';
        $this->resetValidation();
        $this->showSalesAccountModal = true;
    }

    public function saveCommissionSettings(): void
    {
        $this->ensureAdmin();

        $validated = $this->validate([
            'commissionBasis' => ['required', Rule::in(['agent', 'product'])],
            'defaultCommissionRate' => ['required', 'numeric', 'min:0'],
        ]);

        $commissionSettings = app(CommissionService::class)->settings();

        $commissionSettings->update([
            'basis' => $validated['commissionBasis'],
            'default_rate' => $validated['defaultCommissionRate'],
        ]);

        $this->fillCommissionSettings($commissionSettings->fresh());

        session()->flash('success', 'Pengaturan komisi berhasil diperbarui.');
    }

    public function save(): void
    {
        $this->ensureAdmin();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->editingId),
            ],
            'commissionRate' => ['nullable', 'numeric', 'min:0'],
        ];

        if ($this->editingId === null || $this->password !== '' || $this->password_confirmation !== '') {
            $rules['password'] = ['required', 'string', Password::defaults(), 'confirmed'];
        } else {
            $rules['password'] = ['nullable'];
        }

        $validated = $this->validate($rules);

        $this->ensureSalesRoleAndPermissions();

        $attributes = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'commission_rate' => filled($validated['commissionRate'] ?? null) ? $validated['commissionRate'] : null,
        ];

        if (filled($validated['password'] ?? null)) {
            $attributes['password'] = $validated['password'];
        }

        if ($this->editingId === null) {
            DB::transaction(function () use ($attributes): void {
                $salesUser = User::query()->create([
                    ...$attributes,
                    'is_approved' => true,
                ]);

                $salesUser->syncRoles('sales');
            });

            session()->flash('success', 'Akun sales berhasil dibuat.');
        } else {
            $salesUser = User::query()
                ->role('sales')
                ->findOrFail($this->editingId);

            DB::transaction(function () use ($salesUser, $attributes): void {
                $salesUser->update($attributes);
                $salesUser->syncRoles('sales');
            });

            session()->flash('success', 'Akun sales berhasil diperbarui.');
        }

        $this->resetForm();
        $this->resetPage();
    }

    public function delete(int $userId): void
    {
        $this->ensureAdmin();

        $salesUser = User::query()
            ->role('sales')
            ->withCount('sales')
            ->findOrFail($userId);

        if ($salesUser->sales_count > 0) {
            session()->flash('error', 'Akun sales yang sudah memiliki transaksi tidak dapat dihapus.');

            return;
        }

        $salesUser->delete();

        if ($this->editingId === $userId) {
            $this->resetForm();
        }

        session()->flash('success', 'Akun sales berhasil dihapus.');

        $this->resetPage();
    }

    public function approve(int $userId): void
    {
        $this->ensureAdmin();

        $salesUser = User::query()
            ->role('sales')
            ->findOrFail($userId);

        $salesUser->update([
            'is_approved' => true,
        ]);

        session()->flash('success', 'Akun sales berhasil disetujui.');

        $this->resetPage();
    }

    public function resetForm(): void
    {
        $this->reset(['name', 'email', 'password', 'password_confirmation', 'commissionRate', 'editingId', 'showSalesAccountModal']);
        $this->resetValidation();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function sort(string $field): void
    {
        abort_unless(in_array($field, ['name', 'commission_rate', 'sales_count', 'sales_max_date'], true), 404);

        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = match ($field) {
                'sales_count', 'sales_max_date' => 'desc',
                default => 'asc',
            };
        }

        $this->resetPage();
    }

    public function with(): array
    {
        $this->ensureAdmin();
        $defaultCommissionRate = (float) app(CommissionService::class)->defaultRate();

        $salesAccountsQuery = User::query()
            ->role('sales')
            ->withCount('sales')
            ->withMax('sales', 'date')
            ->when($this->search !== '', function ($query): void {
                $searchTerm = '%'.$this->search.'%';

                $query->where(function ($searchQuery) use ($searchTerm): void {
                    $searchQuery
                        ->whereLike('name', $searchTerm)
                        ->orWhereLike('email', $searchTerm);
                });
            });

        if ($this->sortBy === 'commission_rate') {
            $salesAccountsQuery
                ->orderByRaw('COALESCE(commission_rate, ?) '.$this->sortDirection, [$defaultCommissionRate])
                ->orderBy('id');
        } else {
            $salesAccountsQuery
                ->orderBy($this->sortBy, $this->sortDirection)
                ->when($this->sortBy !== 'id', function ($query): void {
                    $query->orderBy('id');
                });
        }

        return [
            'salesAccounts' => (clone $salesAccountsQuery)->paginate(10),
            'salesAccountCount' => User::query()->role('sales')->count(),
            'activeSalesAccountCount' => User::query()->role('sales')->whereHas('sales')->count(),
            'pendingSalesAccountCount' => User::query()->role('sales')->where('is_approved', false)->count(),
        ];
    }

    protected function ensureAdmin(): void
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);
    }

    protected function fillCommissionSettings(?CommissionSetting $commissionSettings = null): void
    {
        $commissionSettings ??= app(CommissionService::class)->settings();

        $this->commissionBasis = $commissionSettings->basis;
        $this->defaultCommissionRate = number_format((float) $commissionSettings->default_rate, 2, '.', '');
    }

    protected function ensureSalesRoleAndPermissions(): Role
    {
        return Role::findOrCreate('sales', 'web');
    }
}; ?>

<section class="flex flex-col gap-6">
    <div class="flex flex-col gap-2">
        <p class="text-sm font-medium uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Admin</p>
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="text-3xl font-semibold text-zinc-950 dark:text-white">Akun sales</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    Buat, perbarui, dan nonaktifkan akun tim sales tanpa menyentuh data transaksi yang sudah tercatat.
                </p>
            </div>
            <div class="flex w-full flex-col gap-3 lg:w-auto lg:items-end">
                <div class="flex flex-wrap gap-3 lg:justify-end">
                    <div class="rounded-full border border-zinc-200 px-4 py-2 text-sm text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">
                        {{ $salesAccountCount }} akun
                    </div>
                    <div class="rounded-full border border-zinc-200 px-4 py-2 text-sm text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">
                        {{ $activeSalesAccountCount }} aktif bertransaksi
                    </div>
                    <div class="rounded-full border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                        {{ $pendingSalesAccountCount }} menunggu persetujuan
                    </div>
                </div>

                <flux:button variant="primary" type="button" wire:click="startCreating" class="w-full justify-center sm:w-auto">
                    Akun sales baru
                </flux:button>
            </div>
        </div>
    </div>

    @include('partials.flash-messages')

    <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <form wire:submit="saveCommissionSettings" class="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
            <div class="space-y-5">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Pengaturan komisi</h2>
                    <p class="mt-1 text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                        Tentukan apakah komisi dihitung berdasarkan akun sales atau berdasarkan produk. Rate default akan dipakai saat tidak ada override khusus.
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block cursor-pointer">
                        <input wire:model.live="commissionBasis" type="radio" value="agent" class="sr-only" />
                        <div class="{{ $commissionBasis === 'agent' ? 'border-zinc-950 bg-zinc-950 text-white dark:border-white dark:bg-white dark:text-zinc-950' : 'border-zinc-200 bg-zinc-50 text-zinc-700 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200' }} rounded-2xl border p-4 transition">
                            <p class="text-sm font-semibold">Berdasarkan agent</p>
                            <p class="{{ $commissionBasis === 'agent' ? 'text-white/75 dark:text-zinc-600' : 'text-zinc-500 dark:text-zinc-400' }} mt-2 text-sm leading-6">
                                Gunakan rate per akun sales. Jika akun tidak punya rate sendiri, sistem memakai rate default.
                            </p>
                        </div>
                    </label>

                    <label class="block cursor-pointer">
                        <input wire:model.live="commissionBasis" type="radio" value="product" class="sr-only" />
                        <div class="{{ $commissionBasis === 'product' ? 'border-emerald-500 bg-emerald-500 text-white dark:text-zinc-950' : 'border-zinc-200 bg-zinc-50 text-zinc-700 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200' }} rounded-2xl border p-4 transition">
                            <p class="text-sm font-semibold">Berdasarkan produk</p>
                            <p class="{{ $commissionBasis === 'product' ? 'text-white/80 dark:text-zinc-900/70' : 'text-zinc-500 dark:text-zinc-400' }} mt-2 text-sm leading-6">
                                Hitung komisi dari rate tiap produk. Produk tanpa rate sendiri akan memakai rate default.
                            </p>
                        </div>
                    </label>
                </div>
            </div>

            <div class="rounded-3xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-800 dark:bg-zinc-950">
                <flux:field>
                    <flux:label>Rate default (%)</flux:label>
                    <flux:input wire:model="defaultCommissionRate" name="defaultCommissionRate" type="number" min="0" step="0.01" placeholder="5.00" />
                    <flux:error name="defaultCommissionRate" />
                </flux:field>

                <div class="mt-4 rounded-2xl border border-zinc-200 bg-white px-4 py-4 text-sm text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300">
                    @if ($commissionBasis === 'agent')
                        Komisi akan mengikuti rate di akun sales. Akun tanpa override memakai {{ number_format((float) $defaultCommissionRate, 2, ',', '.') }}%.
                    @else
                        Komisi akan mengikuti rate di produk. Produk tanpa override memakai {{ number_format((float) $defaultCommissionRate, 2, ',', '.') }}%.
                    @endif
                </div>

                <div class="mt-5 flex justify-end">
                    <flux:button variant="primary" type="submit">
                        Simpan pengaturan komisi
                    </flux:button>
                </div>
            </div>
        </form>
    </div>

    <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 pb-5 dark:border-zinc-800">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Daftar akun sales</h2>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        Setiap akun dapat dipakai untuk entri penjualan. Akun dengan riwayat transaksi tidak bisa dihapus agar data tetap aman.
                    </p>
                </div>

                <div class="w-full lg:max-w-sm">
                    <flux:field>
                        <flux:label>Cari akun sales</flux:label>
                        <flux:input
                            wire:model.live.debounce.300ms="search"
                            name="search"
                            type="text"
                            placeholder="Cari nama atau email"
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
                            <th class="pb-4 font-medium">Email</th>
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
                            <th class="pb-4 font-medium">Status</th>
                            <th class="pb-4 font-medium">
                                <button type="button" wire:click="sort('sales_count')" class="group/sortable inline-flex items-center gap-2 transition hover:text-zinc-900 dark:hover:text-zinc-100">
                                    <span>Transaksi</span>
                                    <span class="text-zinc-400 group-hover/sortable:text-zinc-700 dark:group-hover/sortable:text-zinc-200">
                                        @if ($sortBy === 'sales_count')
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
                                <button type="button" wire:click="sort('sales_max_date')" class="group/sortable inline-flex items-center gap-2 transition hover:text-zinc-900 dark:hover:text-zinc-100">
                                    <span>Terakhir aktif</span>
                                    <span class="text-zinc-400 group-hover/sortable:text-zinc-700 dark:group-hover/sortable:text-zinc-200">
                                        @if ($sortBy === 'sales_max_date')
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
                            <th class="pb-4 text-right font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($salesAccounts as $salesAccount)
                            <tr wire:key="sales-account-{{ $salesAccount->id }}">
                                <td class="py-4 pr-4">
                                    <div class="flex flex-col">
                                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $salesAccount->name }}</span>
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">Role: sales</span>
                                    </div>
                                </td>
                                <td class="py-4 pr-4 text-zinc-600 dark:text-zinc-300">{{ $salesAccount->email }}</td>
                                <td class="py-4 pr-4 text-zinc-600 dark:text-zinc-300">
                                    @if ($salesAccount->commission_rate !== null)
                                        {{ number_format((float) $salesAccount->commission_rate, 2, ',', '.') }}%
                                    @else
                                        Default {{ number_format((float) $defaultCommissionRate, 2, ',', '.') }}%
                                    @endif
                                </td>
                                <td class="py-4 pr-4">
                                    @if ($salesAccount->is_approved)
                                        <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">
                                            Disetujui
                                        </span>
                                    @else
                                        <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                                            Menunggu persetujuan
                                        </span>
                                    @endif
                                </td>
                                <td class="py-4 pr-4 text-zinc-600 dark:text-zinc-300">{{ $salesAccount->sales_count }}</td>
                                <td class="py-4 pr-4 text-zinc-600 dark:text-zinc-300">
                                    @if ($salesAccount->sales_max_date)
                                        {{ \Illuminate\Support\Carbon::parse($salesAccount->sales_max_date)->format('d M Y') }}
                                    @else
                                        Belum ada transaksi
                                    @endif
                                </td>
                                <td class="py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        @if (! $salesAccount->is_approved)
                                            <button
                                                type="button"
                                                wire:click="approve({{ $salesAccount->id }})"
                                                class="rounded-full border border-emerald-300 px-3 py-1.5 font-medium text-emerald-700 transition hover:border-emerald-400 hover:text-emerald-900 dark:border-emerald-500/30 dark:text-emerald-300 dark:hover:border-emerald-400 dark:hover:text-emerald-200"
                                            >
                                                Setujui
                                            </button>
                                        @endif
                                        <button
                                            type="button"
                                            wire:click="edit({{ $salesAccount->id }})"
                                            class="rounded-full border border-zinc-300 px-3 py-1.5 font-medium text-zinc-700 transition hover:border-zinc-400 hover:text-zinc-950 dark:border-zinc-700 dark:text-zinc-200 dark:hover:border-zinc-500 dark:hover:text-white"
                                        >
                                            Ubah
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="delete({{ $salesAccount->id }})"
                                            class="rounded-full border border-rose-300 px-3 py-1.5 font-medium text-rose-700 transition hover:border-rose-400 hover:text-rose-900 dark:border-rose-500/30 dark:text-rose-300 dark:hover:border-rose-400 dark:hover:text-rose-200"
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-10 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $search !== '' ? 'Tidak ada akun sales yang cocok dengan pencarian Anda.' : 'Belum ada akun sales. Gunakan Akun sales baru untuk menambahkan pengguna pertama.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($salesAccounts->hasPages())
                <div class="mt-6">
                    {{ $salesAccounts->links() }}
                </div>
            @endif
        </div>
    </div>

    <flux:modal wire:model="showSalesAccountModal" focusable class="max-w-xl">
        <form wire:submit="save" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $editingId === null ? 'Tambah akun sales' : 'Ubah akun sales' }}</flux:heading>
                <p class="mt-2 text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                    {{ $editingId === null ? 'Siapkan akun baru untuk tim sales Anda.' : 'Perbarui identitas akun. Kosongkan password jika tidak ingin mengubahnya.' }}
                </p>
            </div>

            <flux:field>
                <flux:label>Nama</flux:label>
                <flux:input wire:model="name" name="name" type="text" placeholder="Nama sales" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>Email</flux:label>
                <flux:input wire:model="email" name="email" type="email" placeholder="sales@example.com" />
                <flux:error name="email" />
            </flux:field>

            <div class="grid gap-5 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Password</flux:label>
                    <flux:input wire:model="password" name="password" type="password" placeholder="Kata sandi" />
                    <flux:error name="password" />
                </flux:field>

                <flux:field>
                    <flux:label>Konfirmasi password</flux:label>
                    <flux:input wire:model="password_confirmation" name="password_confirmation" type="password" placeholder="Ulangi kata sandi" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Komisi agent (%)</flux:label>
                <flux:input wire:model="commissionRate" name="commissionRate" type="number" min="0" step="0.01" placeholder="Kosongkan untuk rate default" />
                <flux:error name="commissionRate" />
                <p class="mt-2 text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                    Kosongkan jika akun ini harus mengikuti rate default {{ number_format((float) $defaultCommissionRate, 2, ',', '.') }}%.
                </p>
            </flux:field>

            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="resetForm">
                    Batal
                </flux:button>
                <flux:button variant="primary" type="submit">
                    {{ $editingId === null ? 'Simpan akun' : 'Perbarui akun' }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</section>
