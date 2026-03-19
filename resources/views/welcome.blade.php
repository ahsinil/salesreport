<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @php($title = 'Laporan Penjualan')
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white text-zinc-950 antialiased dark:bg-zinc-950 dark:text-white">
        <div class="relative overflow-hidden">
            <div class="absolute inset-x-0 top-0 h-[28rem] bg-[radial-gradient(circle_at_top,_rgba(16,185,129,0.18),_transparent_55%)] dark:bg-[radial-gradient(circle_at_top,_rgba(52,211,153,0.18),_transparent_55%)]"></div>
            <div class="absolute inset-x-0 top-0 h-px bg-zinc-200 dark:bg-zinc-800"></div>

            <header class="relative mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-6 py-6 lg:px-8">
                <a href="{{ route('home') }}" class="min-w-0" wire:navigate>
                    <x-app-logo class="min-w-0" />
                </a>

                <nav class="flex items-center gap-3">
                    @auth
                        <a
                            href="{{ route('dashboard') }}"
                            class="inline-flex items-center justify-center rounded-full bg-zinc-950 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-200"
                            wire:navigate
                        >
                            Buka dasbor
                        </a>
                    @else
                        <a
                            href="{{ route('login') }}"
                            class="inline-flex items-center justify-center rounded-full border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-900"
                            wire:navigate
                        >
                            Masuk
                        </a>

                        <a
                            href="{{ route('register') }}"
                            class="inline-flex items-center justify-center rounded-full bg-emerald-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-400 dark:text-zinc-950"
                            wire:navigate
                        >
                            Daftar
                        </a>
                    @endauth
                </nav>
            </header>

            <main class="relative mx-auto flex w-full max-w-6xl flex-col gap-10 px-6 pb-16 pt-8 lg:px-8 lg:pb-24 lg:pt-14">
                <section class="grid gap-8 lg:grid-cols-[minmax(0,1.15fr)_24rem] lg:items-start">
                    <div class="space-y-8">
                        <div class="space-y-5">
                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
                                Operasional penjualan
                            </span>

                            <div class="max-w-3xl space-y-4">
                                <h1 class="text-4xl font-semibold tracking-tight text-zinc-950 dark:text-white sm:text-5xl">
                                    Catat penjualan, pantau stok, dan susun laporan bulanan dalam satu alur kerja.
                                </h1>

                                <p class="max-w-2xl text-base leading-7 text-zinc-600 dark:text-zinc-300 sm:text-lg">
                                    Laporan Penjualan membantu tim toko menjaga katalog produk, mencatat transaksi, menghitung komisi, dan mengekspor rekap data tanpa berpindah alat.
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            @auth
                            <a
                                href="{{ route('dashboard') }}"
                                class="inline-flex items-center justify-center rounded-full bg-zinc-950 px-5 py-3 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-200"
                                wire:navigate
                            >
                                Lihat dasbor
                            </a>

                                @if (auth()->user()->can(\App\Support\Permissions::CreateSales))
                                    <a
                                        href="{{ route('sales.create') }}"
                                        class="inline-flex items-center justify-center rounded-full border border-zinc-300 px-5 py-3 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-900"
                                        wire:navigate
                                    >
                                        Catat penjualan
                                    </a>
                                @endif
                            @else
                                <a
                                    href="{{ route('login') }}"
                                    class="inline-flex items-center justify-center rounded-full bg-zinc-950 px-5 py-3 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-200"
                                    wire:navigate
                                >
                                    Masuk ke aplikasi
                                </a>

                                <a
                                    href="{{ route('register') }}"
                                    class="inline-flex items-center justify-center rounded-full border border-zinc-300 px-5 py-3 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-900"
                                    wire:navigate
                                >
                                    Buat akun
                                </a>
                            @endauth
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                                <p class="text-sm font-semibold text-zinc-950 dark:text-white">Katalog rapi</p>
                                <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                                    Produk, harga, dan stok tersusun jelas untuk kebutuhan kasir harian.
                                </p>
                            </div>

                            <div class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                                <p class="text-sm font-semibold text-zinc-950 dark:text-white">Komisi otomatis</p>
                                <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                                    Setiap transaksi langsung menghitung komisi tanpa rekap manual.
                                </p>
                            </div>

                            <div class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                                <p class="text-sm font-semibold text-zinc-950 dark:text-white">Ekspor cepat</p>
                                <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                                    Laporan bisa diunduh ke Excel untuk audit, rekap bulanan, dan arsip.
                                </p>
                            </div>
                        </div>
                    </div>

                    <aside class="rounded-[2rem] border border-zinc-200 bg-zinc-50 p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/80">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500 dark:text-zinc-400">Ringkasan alur</p>
                                <h2 class="mt-2 text-xl font-semibold text-zinc-950 dark:text-white">Tim bisa bergerak lebih cepat dari halaman yang sama.</h2>
                            </div>
                            <span class="rounded-full bg-emerald-500/15 px-3 py-1 text-xs font-medium text-emerald-700 dark:text-emerald-300">Siap dipakai</span>
                        </div>

                        <div class="mt-6 space-y-4">
                            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
                                <p class="text-sm font-semibold text-zinc-950 dark:text-white">1. Kelola produk</p>
                                <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">Tambahkan katalog baru, perbarui harga, dan cek stok minimum sebelum transaksi dimulai.</p>
                            </div>

                            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
                                <p class="text-sm font-semibold text-zinc-950 dark:text-white">2. Catat transaksi</p>
                                <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">Input item penjualan, kurangi stok otomatis, dan simpan nama pelanggan bila dibutuhkan.</p>
                            </div>

                            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
                                <p class="text-sm font-semibold text-zinc-950 dark:text-white">3. Tinjau laporan</p>
                                <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">Pantau omzet, komisi, dan transaksi per periode lalu unduh hasilnya ke file Excel.</p>
                            </div>
                        </div>
                    </aside>
                </section>
            </main>
        </div>

        @fluxScripts
    </body>
</html>
