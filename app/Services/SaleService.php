<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(public CommissionService $commissionService)
    {
    }

    /**
     * @param  array<int, array{product_id:int|string, qty:int|string}>  $items
     */
    public function createSale(User $user, string $customerName, array $items, ?CarbonInterface $date = null): Sale
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Tambahkan minimal satu produk ke penjualan.',
            ]);
        }

        return DB::transaction(function () use ($user, $customerName, $items, $date): Sale {
            $sale = Sale::query()->create([
                'user_id' => $user->id,
                'customer_name' => $customerName,
                'date' => ($date ?? now())->toDateString(),
                'total_amount' => 0,
                'commission_amount' => 0,
            ]);

            $totalAmount = 0.0;
            $commissionLineItems = [];

            foreach ($items as $item) {
                $product = Product::query()
                    ->lockForUpdate()
                    ->findOrFail((int) $item['product_id']);

                $quantity = (int) $item['qty'];

                if ($product->stock < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Stok {$product->name} tidak mencukupi. Tersedia: {$product->stock}.",
                    ]);
                }

                $subtotal = round((float) $product->price * $quantity, 2);

                $sale->items()->create([
                    'product_id' => $product->id,
                    'qty' => $quantity,
                    'price' => $product->price,
                    'subtotal' => $subtotal,
                ]);

                $product->decrement('stock', $quantity);

                $commissionLineItems[] = [
                    'product' => $product,
                    'subtotal' => $subtotal,
                ];

                $totalAmount = round($totalAmount + $subtotal, 2);
            }

            $commissionAmount = $this->commissionService->calculate($user, $commissionLineItems);

            $sale->update([
                'total_amount' => $totalAmount,
                'commission_amount' => $commissionAmount,
            ]);

            return $sale->load(['items.product', 'user']);
        });
    }

    public function voidSale(Sale|int $sale): void
    {
        DB::transaction(function () use ($sale): void {
            $saleModel = Sale::query()
                ->with('items')
                ->findOrFail($sale instanceof Sale ? $sale->getKey() : $sale);

            foreach ($saleModel->items as $item) {
                Product::query()
                    ->lockForUpdate()
                    ->findOrFail($item->product_id)
                    ->increment('stock', $item->qty);
            }

            $saleModel->items()->delete();
            $saleModel->delete();
        });
    }
}
