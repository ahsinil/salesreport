<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProductIndexRequest;
use App\Http\Requests\Api\ProductStoreRequest;
use App\Http\Requests\Api\ProductUpdateRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\CommissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(ProductIndexRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $defaultCommissionRate = app(CommissionService::class)->defaultRate();
        $productsQuery = Product::query()
            ->when(($validated['search'] ?? '') !== '', function ($query) use ($validated): void {
                $query->whereLike('name', '%'.$validated['search'].'%');
            });

        if ($validated['sort_by'] === 'commission_rate') {
            $productsQuery
                ->orderByRaw('COALESCE(commission_rate, ?) '.$validated['sort_direction'], [$defaultCommissionRate])
                ->orderBy('id');
        } else {
            $productsQuery
                ->orderBy($validated['sort_by'], $validated['sort_direction'])
                ->when($validated['sort_by'] !== 'id', function ($query): void {
                    $query->orderBy('id');
                });
        }

        return ProductResource::collection($productsQuery->paginate($validated['per_page']));
    }

    public function store(ProductStoreRequest $request): JsonResponse
    {
        $attributes = $request->validated();

        if (! $request->user()?->hasRole('admin')) {
            unset($attributes['commission_rate']);
        }

        $product = Product::query()->create($attributes);

        return response()->json([
            'data' => new ProductResource($product),
        ], 201);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product);
    }

    public function update(ProductUpdateRequest $request, Product $product): ProductResource
    {
        $attributes = $request->validated();

        if (! $request->user()?->hasRole('admin')) {
            unset($attributes['commission_rate']);
        }

        $product->update($attributes);

        return new ProductResource($product->fresh());
    }

    public function destroy(Product $product): JsonResponse
    {
        if ($product->saleItems()->exists()) {
            return response()->json([
                'message' => 'Produk yang sudah tercatat dalam penjualan tidak dapat dihapus.',
            ], 422);
        }

        $product->delete();

        return response()->json([
            'message' => 'Produk berhasil dihapus.',
        ]);
    }
}
