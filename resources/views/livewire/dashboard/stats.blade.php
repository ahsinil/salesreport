<?php

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\CurrencyFormatter;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public function with(): array
    {
        $user = Auth::user();
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $weekStart = now()->subDays(6)->startOfDay();
        $canViewProducts = $user?->can(Permissions::ViewProducts) ?? false;
        $canViewSalesList = $user?->can(Permissions::ViewSalesList) ?? false;
        $canCreateSales = $user?->can(Permissions::CreateSales) ?? false;
        $canViewReports = $user?->can(Permissions::ViewReports) ?? false;
        $canViewSalesStats = $user?->can(Permissions::ViewSalesStats) ?? false;
        $canViewLatestSales = $user?->can(Permissions::ViewLatestSales) ?? false;

        $quickLinks = collect([
            $canViewProducts ? [
                'label' => 'Produk',
                'description' => 'Kelola harga, stok, dan data produk untuk transaksi.',
                'href' => route('products.index'),
                'current' => false,
            ] : null,
            $user?->hasRole('admin') ? [
                'label' => 'Akun sales',
                'description' => 'Kelola akun tim sales dan rate komisi agent.',
                'href' => route('sales-accounts.index'),
                'current' => false,
            ] : null,
            $canViewSalesList ? [
                'label' => 'Daftar penjualan',
                'description' => 'Tinjau semua transaksi dan detail item dari setiap penjualan.',
                'href' => route('sales.index'),
                'current' => false,
            ] : null,
            // $canCreateSales ? [
            //     'label' => 'Entri penjualan',
            //     'description' => 'Catat transaksi baru dengan estimasi total dan komisi.',
            //     'href' => route('sales.create'),
            //     'current' => false,
            // ] : null,
            $canViewReports ? [
                'label' => 'Laporan',
                'description' => 'Buka rekap periode dan unduh file Excel.',
                'href' => route('reports.index'),
                'current' => false,
            ] : null,
        ])->filter()->values();

        $topProducts = collect();
        $lowStockProducts = collect();
        $weeklyTrend = collect();
        $recentSales = collect();
        $monthlySales = 0.0;
        $monthlyCommission = 0.0;
        $monthlyTransactions = 0;
        $todaySales = 0.0;
        $totalProducts = 0;
        $lowStockCount = 0;
        $topRevenuePeak = 1.0;
        $weeklyPeak = 1.0;
        $weeklyTotal = 0.0;

        if ($canViewSalesStats) {
            $topProducts = SaleItem::query()
                ->select('product_id')
                ->selectRaw('SUM(qty) as total_qty')
                ->selectRaw('SUM(subtotal) as total_revenue')
                ->with('product')
                ->groupBy('product_id')
                ->orderByDesc('total_revenue')
                ->limit(5)
                ->get();

            $weeklySalesTotals = Sale::query()
                ->selectRaw('date, SUM(total_amount) as total')
                ->whereBetween('date', [$weekStart->toDateString(), now()->toDateString()])
                ->groupBy('date')
                ->pluck('total', 'date');

            $weeklyTrend = collect(range(0, 6))->map(function (int $offset) use ($weekStart, $weeklySalesTotals): array {
                $date = CarbonImmutable::parse($weekStart)->addDays($offset);
                $total = (float) ($weeklySalesTotals[$date->toDateString()] ?? 0);

                return [
                    'label' => $date->translatedFormat('D'),
                    'date' => $date->translatedFormat('j M'),
                    'total' => round($total, 2),
                ];
            });

            $lowStockProducts = Product::query()
                ->where('stock', '<=', 5)
                ->orderBy('stock')
                ->limit(5)
                ->get();

            $todaySales = (float) Sale::query()->whereDate('date', $today)->sum('total_amount');
            $monthlySales = (float) Sale::query()->whereBetween('date', [$monthStart, $today])->sum('total_amount');
            $monthlyCommission = (float) Sale::query()->whereBetween('date', [$monthStart, $today])->sum('commission_amount');
            $monthlyTransactions = Sale::query()->whereBetween('date', [$monthStart, $today])->count();
            $totalProducts = Product::query()->count();
            $lowStockCount = Product::query()->where('stock', '<=', 5)->count();
            $topRevenuePeak = max((float) ($topProducts->max('total_revenue') ?? 0), 1);
            $weeklyPeak = max((float) ($weeklyTrend->max('total') ?? 0), 1);
            $weeklyTotal = round((float) $weeklyTrend->sum('total'), 2);
        }

        if ($canViewLatestSales) {
            $recentSales = Sale::query()
                ->with('user')
                ->latest('date')
                ->latest('id')
                ->limit(5)
                ->get();
        }

        return [
            'canViewSalesStats' => $canViewSalesStats,
            'canViewLatestSales' => $canViewLatestSales,
            'todaySales' => $todaySales,
            'monthlySales' => $monthlySales,
            'monthlyCommission' => $monthlyCommission,
            'monthlyTransactions' => $monthlyTransactions,
            'averageOrderValue' => $monthlyTransactions > 0 ? round($monthlySales / $monthlyTransactions, 2) : 0,
            'totalProducts' => $totalProducts,
            'lowStockCount' => $lowStockCount,
            'lowStockProducts' => $lowStockProducts,
            'topProducts' => $topProducts,
            'topRevenuePeak' => $topRevenuePeak,
            'recentSales' => $recentSales,
            'weeklyTrend' => $weeklyTrend,
            'weeklyPeak' => $weeklyPeak,
            'weeklyTotal' => $weeklyTotal,
            'topPerformer' => $topProducts->first(),
            'quickLinks' => $quickLinks,
        ];
    }
}; ?>

<div class="flex flex-col gap-6">
    @if ($quickLinks->isNotEmpty())
        <section class="rounded-[1.9rem] border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-col gap-2">
                <p class="text-sm font-medium uppercase tracking-[0.18em] text-zinc-500 dark:text-zinc-400">Akses cepat</p>
                <h2 class="text-xl font-semibold text-zinc-950 dark:text-white">Menu utama dalam satu baris kartu.</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Gunakan kartu ini untuk pindah ke halaman kerja utama tanpa membuka navigasi samping.</p>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($quickLinks as $quickLink)
                    <a
                        href="{{ $quickLink['href'] }}"
                        wire:navigate
                        class="{{ $quickLink['current'] ? 'border-zinc-950 bg-zinc-950 text-white dark:border-white dark:bg-white dark:text-zinc-950' : 'border-zinc-200 bg-zinc-50 text-zinc-700 hover:border-zinc-300 hover:bg-zinc-100 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200 dark:hover:border-zinc-700 dark:hover:bg-zinc-900' }} rounded-[1.4rem] border p-4 transition"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold">{{ $quickLink['label'] }}</p>
                            @if ($quickLink['current'])
                                <span class="rounded-full bg-white/15 px-2.5 py-1 text-[11px] font-medium uppercase tracking-[0.18em] text-white dark:bg-zinc-950/10 dark:text-zinc-600">
                                    Saat ini
                                </span>
                            @endif
                        </div>
                        <p class="{{ $quickLink['current'] ? 'text-white/70 dark:text-zinc-600' : 'text-zinc-500 dark:text-zinc-400' }} mt-2 text-sm leading-6">
                            {{ $quickLink['description'] }}
                        </p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($canViewSalesStats)
        <section class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-4">
            <article class="rounded-[1.75rem] border border-zinc-200 bg-gradient-to-br from-white to-zinc-50 p-5 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:to-zinc-950">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Penjualan hari ini</p>
                <p class="mt-3 text-3xl font-semibold text-zinc-950 dark:text-white">{{ CurrencyFormatter::rupiah($todaySales) }}</p>
                <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Gambaran cepat aktivitas transaksi hari ini.</p>
            </article>

            <article class="rounded-[1.75rem] border border-zinc-200 bg-gradient-to-br from-white to-emerald-50 p-5 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:to-emerald-500/10">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Pendapatan bulan ini</p>
                <p class="mt-3 text-3xl font-semibold text-zinc-950 dark:text-white">{{ CurrencyFormatter::rupiah($monthlySales) }}</p>
                <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ $monthlyTransactions }} transaksi tercatat bulan ini.</p>
            </article>

            <article class="rounded-[1.75rem] border border-zinc-200 bg-gradient-to-br from-white to-sky-50 p-5 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:to-sky-500/10">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Komisi bulan ini</p>
                <p class="mt-3 text-3xl font-semibold text-zinc-950 dark:text-white">{{ CurrencyFormatter::rupiah($monthlyCommission) }}</p>
                <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Dihitung otomatis berdasarkan aturan komisi yang sedang aktif.</p>
            </article>

            <article class="rounded-[1.75rem] border border-zinc-200 bg-gradient-to-br from-white to-amber-50 p-5 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:to-amber-500/10">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Kondisi stok</p>
                <p class="mt-3 text-3xl font-semibold text-zinc-950 dark:text-white">{{ number_format($totalProducts) }}</p>
                <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $lowStockCount }} produk berada di bawah ambang stok minimum.
                </p>
            </article>
        </section>
    @endif

    @if ($canViewSalesStats || $canViewLatestSales)
        <section class="grid gap-4 {{ $canViewSalesStats && $canViewLatestSales ? 'xl:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]' : '' }}">
            @if ($canViewSalesStats)
                <div class="rounded-[1.9rem] border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-xl font-semibold text-zinc-950 dark:text-white">Produk unggulan</h2>
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Penyumbang pendapatan tertinggi dari penjualan yang tercatat.</p>
                        </div>
                    </div>

                    <div class="mt-6 space-y-4">
                        @forelse ($topProducts as $index => $topProduct)
                            @php($revenuePercent = min(($topProduct->total_revenue / $topRevenuePeak) * 100, 100))
                            <div class="rounded-[1.5rem] border border-zinc-200 bg-zinc-50/80 p-4 dark:border-zinc-800 dark:bg-zinc-950/70">
                                <div class="flex items-center gap-4">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-zinc-950 text-sm font-semibold text-white dark:bg-white dark:text-zinc-950">
                                        {{ $index + 1 }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-4">
                                            <div class="min-w-0">
                                                <p class="truncate font-medium text-zinc-950 dark:text-white">{{ $topProduct->product?->name ?? 'Produk tidak diketahui' }}</p>
                                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $topProduct->total_qty }} unit terjual</p>
                                            </div>
                                            <p class="shrink-0 text-sm font-semibold text-zinc-700 dark:text-zinc-200">{{ CurrencyFormatter::rupiah($topProduct->total_revenue) }}</p>
                                        </div>

                                        <div class="mt-4 h-2 rounded-full bg-zinc-200 dark:bg-zinc-800">
                                            <div class="h-2 rounded-full bg-gradient-to-r from-emerald-400 to-sky-400" style="width: {{ $revenuePercent }}%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-[1.5rem] border border-dashed border-zinc-300 px-6 py-10 text-center dark:border-zinc-700">
                                <p class="text-base font-medium text-zinc-900 dark:text-white">Belum ada produk unggulan</p>
                                <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Buat penjualan pertama Anda untuk mengisi peringkat produk.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            @endif

            <div class="grid gap-4">
                @if ($canViewSalesStats)
                    <div class="rounded-[1.9rem] border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h2 class="text-xl font-semibold text-zinc-950 dark:text-white">Pantauan stok menipis</h2>
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Produk yang memerlukan perhatian untuk restok.</p>
                            </div>
                            <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                                {{ $lowStockCount }} ditandai
                            </span>
                        </div>

                        <div class="mt-5 space-y-3">
                            @forelse ($lowStockProducts as $product)
                                <div class="flex items-center justify-between rounded-[1.25rem] border border-zinc-200 bg-zinc-50/80 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-950/70">
                                    <div>
                                        <p class="font-medium text-zinc-950 dark:text-white">{{ $product->name }}</p>
                                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Lakukan restok sebelum lonjakan penjualan berikutnya.</p>
                                    </div>
                                    <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $product->stock <= 2 ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' }}">
                                        {{ $product->stock }} left
                                    </span>
                                </div>
                            @empty
                                <div class="rounded-[1.5rem] border border-dashed border-zinc-300 px-6 py-8 text-center dark:border-zinc-700">
                                    <p class="font-medium text-zinc-900 dark:text-white">Kondisi stok aman</p>
                                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Semua stok berada di atas ambang peringatan saat ini.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endif

                @if ($canViewLatestSales)
                    <div class="rounded-[1.9rem] border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h2 class="text-xl font-semibold text-zinc-950 dark:text-white">Penjualan terbaru</h2>
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Transaksi terbaru yang masuk ke sistem.</p>
                            </div>
                        </div>

                        <div class="mt-5 space-y-3">
                            @forelse ($recentSales as $sale)
                                <div class="rounded-[1.25rem] border border-zinc-200 bg-zinc-50/80 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-950/70">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <p class="truncate font-medium text-zinc-950 dark:text-white">{{ $sale->customer_name }}</p>
                                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $sale->user->name }} • {{ $sale->date->translatedFormat('j M Y') }}</p>
                                        </div>
                                        <p class="shrink-0 text-sm font-semibold text-zinc-700 dark:text-zinc-200">{{ CurrencyFormatter::rupiah($sale->total_amount) }}</p>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-[1.5rem] border border-dashed border-zinc-300 px-6 py-8 text-center dark:border-zinc-700">
                                    <p class="font-medium text-zinc-900 dark:text-white">Belum ada transaksi terbaru</p>
                                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Gunakan halaman entri penjualan untuk mulai membangun riwayat transaksi.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>
        </section>
    @endif

    @if ($quickLinks->isEmpty() && ! $canViewSalesStats && ! $canViewLatestSales)
        <section class="rounded-[1.9rem] border border-dashed border-zinc-300 bg-white px-6 py-10 text-center shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-base font-medium text-zinc-900 dark:text-white">Belum ada modul yang diizinkan untuk akun ini.</p>
            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Minta admin menyalakan hak akses yang diperlukan dari halaman akun sales.</p>
        </section>
    @endif
</div>
