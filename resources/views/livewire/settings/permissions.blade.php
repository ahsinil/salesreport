<?php

use App\Support\Permissions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

new #[Layout('components.layouts.app')] class extends Component {
    public array $salesPermissions = [];

    public function mount(): void
    {
        $this->ensureAdmin();
        $this->fillSalesPermissions();
    }

    public function saveSalesPermissions(): void
    {
        $this->ensureAdmin();

        $availablePermissions = array_keys(Permissions::salesOptions());
        $validated = $this->validate([
            'salesPermissions' => ['array'],
            'salesPermissions.*' => ['string', Rule::in($availablePermissions)],
        ]);

        $this->syncSalesPermissions(
            $validated['salesPermissions'] ?? [],
            'Hak akses sales berhasil diperbarui.',
        );
    }

    public function resetSalesPermissions(): void
    {
        $this->ensureAdmin();

        $this->syncSalesPermissions(
            Permissions::salesDefaults(),
            'Hak akses sales dikembalikan ke bawaan.',
        );
    }

    public function resetAdminPermissions(): void
    {
        $this->ensureAdmin();

        $adminRole = $this->ensureRoleAndPermissions('admin');
        $adminRole->syncPermissions(Permissions::all());

        session()->flash('success', 'Hak akses admin dikembalikan ke bawaan.');
    }

    public function with(): array
    {
        $this->ensureAdmin();

        return [
            'availableSalesPermissions' => Permissions::salesOptions(),
        ];
    }

    protected function ensureAdmin(): void
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);
    }

    protected function fillSalesPermissions(?Role $salesRole = null): void
    {
        $salesRole ??= $this->ensureSalesRoleAndPermissions();
        $salesRole->loadMissing('permissions');

        $grantedPermissions = $salesRole->permissions->pluck('name')->all();

        $this->salesPermissions = collect(array_keys(Permissions::salesOptions()))
            ->filter(fn (string $permission): bool => in_array($permission, $grantedPermissions, true))
            ->values()
            ->all();
    }

    protected function ensureSalesRoleAndPermissions(): Role
    {
        return $this->ensureRoleAndPermissions('sales');
    }

    protected function ensureRoleAndPermissions(string $roleName): Role
    {
        foreach (Permissions::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        return Role::findOrCreate($roleName, 'web');
    }

    /**
     * @param  list<string>  $permissionNames
     */
    protected function syncSalesPermissions(array $permissionNames, string $message): void
    {
        $salesRole = $this->ensureSalesRoleAndPermissions();
        $salesRole->syncPermissions($permissionNames);

        $this->fillSalesPermissions($salesRole->fresh('permissions'));

        session()->flash('success', $message);
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout
        heading="Hak akses sales"
        subheading="Atur menu dan aksi apa saja yang tersedia untuk role sales"
        contentWidthClass="max-w-5xl"
    >
        @include('partials.flash-messages')

        <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <form wire:submit="saveSalesPermissions" class="space-y-6">
                <div class="space-y-5">
                    <div class="grid gap-3">
                        @foreach ($availableSalesPermissions as $permissionName => $permission)
                            @php($enabled = in_array($permissionName, $salesPermissions, true))

                            <label class="{{ $enabled ? 'border-zinc-950 bg-zinc-50 dark:border-white dark:bg-zinc-950' : 'border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900' }} flex cursor-pointer items-start gap-4 rounded-2xl border p-4 transition">
                                <input
                                    wire:model.live="salesPermissions"
                                    type="checkbox"
                                    value="{{ $permissionName }}"
                                    class="mt-1 h-4 w-4 rounded border-zinc-300 text-zinc-950 focus:ring-zinc-950 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white dark:focus:ring-white"
                                />

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $permission['label'] }}</p>

                                        <span class="{{ $enabled ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200' : 'border-zinc-200 bg-zinc-50 text-zinc-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-400' }} inline-flex rounded-full border px-3 py-1 text-xs font-medium">
                                            {{ $enabled ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </div>

                                    <p class="mt-2 text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                                        {{ $permission['description'] }}
                                    </p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex flex-col gap-3 border-t border-zinc-200 pt-6 sm:flex-row sm:justify-end dark:border-zinc-800">
                    <flux:button variant="ghost" type="button" wire:click="resetSalesPermissions">
                        Reset ke bawaan
                    </flux:button>

                    <flux:button variant="primary" type="submit">
                        Simpan hak akses
                    </flux:button>
                </div>
            </form>
        </div>

        <div class="mt-6 rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Reset hak akses admin</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                        Gunakan aksi ini untuk mengembalikan role admin ke semua izin aplikasi jika ada perubahan yang tidak sengaja.
                    </p>
                </div>

                <flux:button variant="ghost" type="button" wire:click="resetAdminPermissions">
                    Reset hak akses admin
                </flux:button>
            </div>
        </div>
    </x-settings.layout>
</section>
