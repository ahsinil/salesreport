<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $targetProductCount = 220;
        $targetSalesCount = 120;

        $this->call([
            CommissionSettingSeeder::class,
            RoleAndPermissionSeeder::class,
            ProductSeeder::class,
        ]);

        $demoSalesUsers = collect([
            [
                'name' => 'Alya Pratama',
                'email' => 'sales.alpha@example.com',
                'commission_rate' => 4.5,
            ],
            [
                'name' => 'Bima Saputra',
                'email' => 'sales.bravo@example.com',
                'commission_rate' => 5.5,
            ],
            [
                'name' => 'Citra Lestari',
                'email' => 'sales.charlie@example.com',
                'commission_rate' => 6.75,
            ],
        ])->map(function (array $salesUserData): User {
            $salesUser = User::query()->updateOrCreate(
                ['email' => $salesUserData['email']],
                [
                    'name' => $salesUserData['name'],
                    'password' => 'password',
                    'is_approved' => true,
                    'commission_rate' => $salesUserData['commission_rate'],
                ],
            );

            $salesUser->syncRoles('sales');

            return $salesUser;
        });

        $baseProducts = collect([
            ['name' => 'Widget C', 'price' => 35000, 'stock' => 240, 'commission_rate' => 4.25],
            ['name' => 'Widget D', 'price' => 65000, 'stock' => 220, 'commission_rate' => 4.75],
            ['name' => 'Gadget Prime', 'price' => 175000, 'stock' => 180, 'commission_rate' => 6.50],
            ['name' => 'Gadget Ultra', 'price' => 225000, 'stock' => 160, 'commission_rate' => 7.00],
            ['name' => 'Tool Max', 'price' => 85000, 'stock' => 210, 'commission_rate' => 5.25],
            ['name' => 'Accessory Pack', 'price' => 12000, 'stock' => 300, 'commission_rate' => 3.50],
            ['name' => 'Starter Bundle', 'price' => 99000, 'stock' => 190, 'commission_rate' => 5.00],
            ['name' => 'Premium Bundle', 'price' => 275000, 'stock' => 140, 'commission_rate' => 7.50],
        ]);

        $catalogProducts = collect(range(1, $targetProductCount - $baseProducts->count() - 5))
            ->map(function (int $index): array {
                return [
                    'name' => sprintf('Demo Product %03d', $index),
                    'price' => 10000 + ($index * 1750),
                    'stock' => 120 + (($index % 6) * 20),
                    'commission_rate' => round(3 + (($index % 8) * 0.5), 2),
                ];
            });

        $baseProducts
            ->concat($catalogProducts)
            ->each(function (array $product): void {
            Product::query()->updateOrCreate(
                ['name' => $product['name']],
                $product,
            );
        });

        $existingDemoSalesCount = Sale::query()
            ->whereIn('user_id', $demoSalesUsers->pluck('id'))
            ->count();

        if ($existingDemoSalesCount >= $targetSalesCount) {
            return;
        }

        $saleService = app(SaleService::class);
        $customerNames = [
            'PT Nusantara Abadi',
            'CV Maju Lancar',
            'Toko Sentosa',
            'UD Sinar Jaya',
            'Berkah Retail',
            'Mandiri Elektronik',
            'Surya Teknik',
            'Prima Grosir',
            'Mitra Usaha',
            'Sahabat Niaga',
            'Karya Mandiri',
            'Tirta Komersial',
            'Atlas Distribusi',
            'Cakra Niaga',
            'Mega Persada',
            'Lestari Digital',
            'Pilar Utama',
            'Harmoni Trading',
        ];

        foreach (range(1, $targetSalesCount - $existingDemoSalesCount) as $saleNumber) {
            $availableProducts = Product::query()
                ->where('stock', '>', 0)
                ->get();

            if ($availableProducts->isEmpty()) {
                break;
            }

            $selectedProducts = $availableProducts
                ->shuffle()
                ->take(fake()->numberBetween(1, min(4, $availableProducts->count())));

            $items = $selectedProducts
                ->map(function (Product $product): array {
                    $maxQuantity = min(4, max(1, $product->stock));

                    return [
                        'product_id' => (string) $product->id,
                        'qty' => (string) fake()->numberBetween(1, $maxQuantity),
                    ];
                })
                ->all();

            $saleService->createSale(
                $demoSalesUsers->random(),
                fake()->randomElement($customerNames),
                $items,
                now()->subDays(fake()->numberBetween(0, 90)),
            );
        }
    }
}
