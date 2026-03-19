<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-950 dark:bg-zinc-950 dark:text-white">
        <flux:sidebar sticky stashable class="border-r border-zinc-200 bg-white px-4 py-4 dark:border-zinc-800 dark:bg-zinc-900">
            <flux:sidebar.toggle class="rounded-full border border-zinc-200 bg-white text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 lg:hidden" icon="x-mark" />

            <div class="flex h-full flex-col gap-4">
                <a href="{{ route('dashboard') }}" class="block rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-950" wire:navigate>
                    <x-app-logo class="min-w-0" />
                </a>

                <div class="space-y-1.5">
                    <p class="px-2 text-[11px] font-semibold uppercase tracking-[0.22em] text-zinc-500 dark:text-zinc-400">Navigasi</p>

                    <nav class="space-y-1.5">
                        <a
                            href="{{ route('dashboard') }}"
                            wire:navigate
                            class="{{ request()->routeIs('dashboard') ? 'bg-zinc-950 text-white dark:bg-white dark:text-zinc-950' : 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800/80' }} flex items-center justify-between gap-3 rounded-xl px-3 py-3 transition"
                        >
                            <div class="min-w-0">
                                <p class="text-sm font-medium">Dasbor</p>
                            </div>
                            @if (request()->routeIs('dashboard'))
                                <span class="text-xs font-medium text-white/70 dark:text-zinc-500">Saat ini</span>
                            @endif
                        </a>

                        @can(\App\Support\Permissions::ViewProducts)
                            <a
                                href="{{ route('products.index') }}"
                                wire:navigate
                                class="{{ request()->routeIs('products.*') ? 'bg-zinc-950 text-white dark:bg-white dark:text-zinc-950' : 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800/80' }} flex items-center justify-between gap-3 rounded-xl px-3 py-3 transition"
                            >
                                <div class="min-w-0">
                                    <p class="text-sm font-medium">Produk</p>
                                </div>
                                @if (request()->routeIs('products.*'))
                                    <span class="text-xs font-medium text-white/70 dark:text-zinc-500">Saat ini</span>
                                @endif
                            </a>
                        @endcan

                        @if (auth()->user()->hasRole('admin'))
                            <a
                                href="{{ route('sales-accounts.index') }}"
                                wire:navigate
                                class="{{ request()->routeIs('sales-accounts.*') ? 'bg-zinc-950 text-white dark:bg-white dark:text-zinc-950' : 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800/80' }} flex items-center justify-between gap-3 rounded-xl px-3 py-3 transition"
                            >
                                <div class="min-w-0">
                                    <p class="text-sm font-medium">Akun sales</p>
                                </div>
                                @if (request()->routeIs('sales-accounts.*'))
                                    <span class="text-xs font-medium text-white/70 dark:text-zinc-500">Saat ini</span>
                                @endif
                            </a>
                        @endif

                        @can(\App\Support\Permissions::ViewSalesList)
                            <a
                                href="{{ route('sales.index') }}"
                                wire:navigate
                                class="{{ request()->routeIs('sales.index') ? 'bg-zinc-950 text-white dark:bg-white dark:text-zinc-950' : 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800/80' }} flex items-center justify-between gap-3 rounded-xl px-3 py-3 transition"
                            >
                                <div class="min-w-0">
                                    <p class="text-sm font-medium">Daftar penjualan</p>
                                </div>
                                @if (request()->routeIs('sales.index'))
                                    <span class="text-xs font-medium text-white/70 dark:text-zinc-500">Saat ini</span>
                                @endif
                            </a>
                        @endcan

                        @can(\App\Support\Permissions::CreateSales)
                            <a
                                href="{{ route('sales.create') }}"
                                wire:navigate
                                class="{{ request()->routeIs('sales.create') ? 'bg-zinc-950 text-white dark:bg-white dark:text-zinc-950' : 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800/80' }} flex items-center justify-between gap-3 rounded-xl px-3 py-3 transition"
                            >
                                <div class="min-w-0">
                                    <p class="text-sm font-medium">Entri penjualan</p>
                                </div>
                                @if (request()->routeIs('sales.create'))
                                    <span class="text-xs font-medium text-white/70 dark:text-zinc-500">Saat ini</span>
                                @endif
                            </a>
                        @endcan

                        @can(\App\Support\Permissions::ViewReports)
                            <a
                                href="{{ route('reports.index') }}"
                                wire:navigate
                                class="{{ request()->routeIs('reports.*') ? 'bg-emerald-500 text-white dark:bg-emerald-400 dark:text-zinc-950' : 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800/80' }} flex items-center justify-between gap-3 rounded-xl px-3 py-3 transition"
                            >
                                <div class="min-w-0">
                                    <p class="text-sm font-medium">Laporan</p>
                                </div>
                                @if (request()->routeIs('reports.*'))
                                    <span class="text-xs font-medium text-white/80 dark:text-zinc-700">Saat ini</span>
                                @endif
                            </a>
                        @endcan
                    </nav>
                </div>

                <flux:spacer />

                <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-zinc-950 text-sm font-semibold text-white dark:bg-white dark:text-zinc-950">
                            {{ auth()->user()->initials() }}
                        </span>

                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-zinc-950 dark:text-white">{{ auth()->user()->name }}</p>
                            <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ auth()->user()->email }}</p>
                        </div>
                    </div>

                    <div class="mt-4 flex gap-2">
                        <a
                            href="/settings/profile"
                            wire:navigate
                            class="inline-flex flex-1 items-center justify-center rounded-full border border-zinc-200 px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-800"
                        >
                            Pengaturan
                        </a>

                        <form method="POST" action="{{ route('logout') }}" class="flex-1">
                            @csrf
                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center rounded-full bg-zinc-950 px-3 py-2 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-200"
                            >
                                {{ __('Log Out') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="border-b border-zinc-200 bg-white px-4 py-3 lg:hidden dark:border-zinc-800 dark:bg-zinc-900">
            <flux:sidebar.toggle class="rounded-full border border-zinc-200 bg-white text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 lg:hidden" icon="bars-2" inset="left" />

            <a href="{{ route('dashboard') }}" class="ml-3 min-w-0" wire:navigate>
                <x-app-logo class="min-w-0" />
            </a>

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item href="/settings/profile" icon="cog" wire:navigate>Pengaturan</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
