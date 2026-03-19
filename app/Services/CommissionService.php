<?php

namespace App\Services;

use App\Models\CommissionSetting;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;

class CommissionService
{
    public function settings(): CommissionSetting
    {
        $settings = CommissionSetting::query()->first();

        if ($settings !== null) {
            return $settings;
        }

        return CommissionSetting::query()->create([
            'basis' => 'agent',
            'default_rate' => 5,
        ]);
    }

    /**
     * @param  iterable<int, array{product?:Product|null, subtotal:float|int|string}>  $lineItems
     */
    public function calculate(User $salesUser, iterable $lineItems): float
    {
        $settings = $this->settings();
        $resolvedLineItems = collect($lineItems)
            ->map(function (array $lineItem): array {
                $product = $lineItem['product'] ?? null;

                return [
                    'product' => $product instanceof Product ? $product : null,
                    'subtotal' => round((float) ($lineItem['subtotal'] ?? 0), 2),
                ];
            });

        if ($settings->basis === 'product') {
            return $this->calculateProductCommission($resolvedLineItems, (float) $settings->default_rate);
        }

        $totalAmount = $resolvedLineItems->sum('subtotal');

        return $this->applyRate($totalAmount, $this->resolveAgentRate($salesUser, $settings));
    }

    public function resolveAgentRate(User $salesUser, ?CommissionSetting $settings = null): float
    {
        $settings ??= $this->settings();

        return round((float) ($salesUser->commission_rate ?? $settings->default_rate), 2);
    }

    public function defaultRate(?CommissionSetting $settings = null): float
    {
        $settings ??= $this->settings();

        return round((float) $settings->default_rate, 2);
    }

    /**
     * @param  Collection<int, array{product:?Product, subtotal:float}>  $lineItems
     */
    protected function calculateProductCommission(Collection $lineItems, float $defaultRate): float
    {
        return round($lineItems->sum(function (array $lineItem) use ($defaultRate): float {
            $productRate = $lineItem['product']?->commission_rate;
            $rate = $productRate !== null ? (float) $productRate : $defaultRate;

            return $this->applyRate($lineItem['subtotal'], $rate);
        }), 2);
    }

    protected function applyRate(float|int|string $subtotal, float|int|string $rate): float
    {
        return round((float) $subtotal * ((float) $rate / 100), 2);
    }
}
