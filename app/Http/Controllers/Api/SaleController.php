<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SaleIndexRequest;
use App\Http\Requests\Api\SaleStoreRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SaleController extends Controller
{
    public function index(SaleIndexRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $sortBy = $validated['sort_by'] === 'item_count' ? 'items_sum_qty' : $validated['sort_by'];

        $sales = Sale::query()
            ->with(['items.product', 'user'])
            ->withSum('items', 'qty')
            ->when(($validated['search'] ?? '') !== '', function (Builder $query) use ($validated): void {
                $searchTerm = '%'.$validated['search'].'%';

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
            });

        if ($sortBy === 'salesperson') {
            $sales->orderBy(
                User::query()
                    ->select('name')
                    ->whereColumn('users.id', 'sales.user_id'),
                $validated['sort_direction'],
            );
        } else {
            $sales->orderBy($sortBy, $validated['sort_direction']);
        }

        if ($sortBy !== 'id') {
            $sales->orderBy('id', 'desc');
        }

        return SaleResource::collection($sales->paginate($validated['per_page']));
    }

    public function store(SaleStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $date = isset($validated['date']) ? CarbonImmutable::parse($validated['date']) : null;
        $sale = app(SaleService::class)->createSale(
            $request->user(),
            $validated['customer_name'],
            $validated['items'],
            $date,
        );

        return response()->json([
            'data' => new SaleResource($sale),
        ], 201);
    }

    public function show(Sale $sale): SaleResource
    {
        return new SaleResource($sale->load(['items.product', 'user'])->loadSum('items', 'qty'));
    }

    public function destroy(Sale $sale): JsonResponse
    {
        app(SaleService::class)->voidSale($sale);

        return response()->json([
            'message' => 'Penjualan berhasil dibatalkan.',
        ]);
    }
}
