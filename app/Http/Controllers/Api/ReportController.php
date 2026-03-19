<?php

namespace App\Http\Controllers\Api;

use App\Exports\SalesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReportFilterRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function index(ReportFilterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $sales = $this->salesQuery($validated)->paginate($validated['per_page']);
        $chart = Sale::query()
            ->selectRaw('date, SUM(total_amount) as total')
            ->whereBetween('date', [$validated['start_date'], $validated['end_date']])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($sale): array => [
                'day' => Carbon::parse($sale->date)->translatedFormat('j M'),
                'date' => Carbon::parse($sale->date)->toDateString(),
                'total' => round((float) $sale->total, 2),
            ])
            ->values()
            ->all();

        return response()->json([
            'filters' => [
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'search' => $validated['search'] ?? '',
                'sort_by' => $validated['sort_by'],
                'sort_direction' => $validated['sort_direction'],
                'per_page' => $validated['per_page'],
            ],
            'summary' => [
                'total_sales' => (float) Sale::query()->whereBetween('date', [$validated['start_date'], $validated['end_date']])->sum('total_amount'),
                'total_commission' => (float) Sale::query()->whereBetween('date', [$validated['start_date'], $validated['end_date']])->sum('commission_amount'),
                'transaction_count' => Sale::query()->whereBetween('date', [$validated['start_date'], $validated['end_date']])->count(),
            ],
            'chart' => $chart,
            'sales' => SaleResource::collection($sales)->toResponse($request)->getData(true),
        ]);
    }

    public function export(ReportFilterRequest $request): BinaryFileResponse
    {
        $validated = $request->validated();

        return Excel::download(
            new SalesExport($validated['start_date'], $validated['end_date']),
            'laporan-penjualan.xlsx',
        );
    }

    /**
     * @param  array{start_date:string,end_date:string,search?:string,sort_by:string,sort_direction:string,per_page:int}  $validated
     */
    protected function salesQuery(array $validated): Builder
    {
        $sortBy = $validated['sort_by'] === 'item_count' ? 'items_sum_qty' : $validated['sort_by'];
        $query = Sale::query()
            ->with(['items.product', 'user'])
            ->withSum('items', 'qty')
            ->whereBetween('date', [$validated['start_date'], $validated['end_date']])
            ->when(($validated['search'] ?? '') !== '', function (Builder $query) use ($validated): void {
                $searchTerm = '%'.$validated['search'].'%';

                $query->where(function (Builder $searchQuery) use ($searchTerm): void {
                    $searchQuery
                        ->whereLike('customer_name', $searchTerm)
                        ->orWhereHas('user', function (Builder $userQuery) use ($searchTerm): void {
                            $userQuery->whereLike('name', $searchTerm);
                        });
                });
            });

        if ($sortBy === 'salesperson') {
            $query->orderBy(
                User::query()
                    ->select('name')
                    ->whereColumn('users.id', 'sales.user_id'),
                $validated['sort_direction'],
            );
        } else {
            $query->orderBy($sortBy, $validated['sort_direction']);
        }

        if ($sortBy !== 'id') {
            $query->orderBy('id', 'desc');
        }

        return $query;
    }
}
