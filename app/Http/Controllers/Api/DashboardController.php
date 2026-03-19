<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\SaleResource;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $weekStart = now()->subDays(6)->startOfDay();
        $canViewSalesStats = $user?->can(Permissions::ViewSalesStats) ?? false;
        $canViewLatestSales = $user?->can(Permissions::ViewLatestSales) ?? false;

        $response = [
            'permissions' => [
                'view_products' => $user?->can(Permissions::ViewProducts) ?? false,
                'view_sales_list' => $user?->can(Permissions::ViewSalesList) ?? false,
                'create_sales' => $user?->can(Permissions::CreateSales) ?? false,
                'view_reports' => $user?->can(Permissions::ViewReports) ?? false,
                'view_sales_stats' => $canViewSalesStats,
                'view_latest_sales' => $canViewLatestSales,
                'manage_sales_accounts' => $user?->hasRole('admin') ?? false,
            ],
            'stats' => null,
            'weekly_trend' => [],
            'top_products' => [],
            'low_stock_products' => [],
            'recent_sales' => [],
        ];

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
                    'date' => $date->toDateString(),
                    'display_date' => $date->translatedFormat('j M'),
                    'total' => round($total, 2),
                ];
            })->values();

            $lowStockProducts = Product::query()
                ->where('stock', '<=', 5)
                ->orderBy('stock')
                ->limit(5)
                ->get();

            $monthlySales = (float) Sale::query()->whereBetween('date', [$monthStart, $today])->sum('total_amount');
            $monthlyTransactions = Sale::query()->whereBetween('date', [$monthStart, $today])->count();

            $response['stats'] = [
                'today_sales' => (float) Sale::query()->whereDate('date', $today)->sum('total_amount'),
                'monthly_sales' => $monthlySales,
                'monthly_commission' => (float) Sale::query()->whereBetween('date', [$monthStart, $today])->sum('commission_amount'),
                'monthly_transactions' => $monthlyTransactions,
                'average_order_value' => $monthlyTransactions > 0 ? round($monthlySales / $monthlyTransactions, 2) : 0.0,
                'total_products' => Product::query()->count(),
                'low_stock_count' => Product::query()->where('stock', '<=', 5)->count(),
            ];
            $response['weekly_trend'] = $weeklyTrend->all();
            $response['top_products'] = $topProducts->map(function (SaleItem $saleItem): array {
                return [
                    'product_id' => $saleItem->product_id,
                    'product_name' => $saleItem->product?->name,
                    'total_qty' => (int) $saleItem->total_qty,
                    'total_revenue' => (float) $saleItem->total_revenue,
                ];
            })->all();
            $response['low_stock_products'] = ProductResource::collection($lowStockProducts)->toResponse($request)->getData(true)['data'];
        }

        if ($canViewLatestSales) {
            $recentSales = Sale::query()
                ->with(['items.product', 'user'])
                ->latest('date')
                ->latest('id')
                ->limit(5)
                ->get();

            $response['recent_sales'] = SaleResource::collection($recentSales)->toResponse($request)->getData(true)['data'];
        }

        return response()->json($response);
    }
}
