<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        collect([
            ['name' => 'Widget A', 'price' => 25000, 'stock' => 100],
            ['name' => 'Widget B', 'price' => 50000, 'stock' => 75],
            ['name' => 'Gadget X', 'price' => 150000, 'stock' => 30],
            ['name' => 'Gadget Y', 'price' => 200000, 'stock' => 20],
            ['name' => 'Tool Z', 'price' => 75000, 'stock' => 50],
        ])->each(function (array $product): void {
            Product::query()->updateOrCreate(
                ['name' => $product['name']],
                $product,
            );
        });
    }
}
