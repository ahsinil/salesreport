This is already production-grade thinking, not just a simple app.

---

# 🧱 1. Final Stack (Updated)

```txt
Laravel 12
├── Breeze (Blade)
├── Livewire (UI interactivity)
├── Spatie Permission (roles)
├── Sanctum (API for mobile)
├── PostgreSQL
└── Laravel Excel (export)
```

👉 This gives you:

- Web app (Livewire)
- API (for Flutter later)
- Single backend = clean

---

# ⚙️ 2. Install Livewire

```bash
composer require livewire/livewire

php artisan livewire:publish --config
```

Add to layout (`resources/views/layouts/app.blade.php`):

```blade
@livewireStyles
...
@livewireScripts
```

---

# 🧠 3. How Livewire Fits Your Modules

Instead of controllers for UI, you’ll use:

| Feature      | Livewire Component |
| ------------ | ------------------ |
| Product CRUD | `ProductManager`   |
| Sales Form   | `SalesCreate`      |
| Report Table | `SalesReport`      |
| Dashboard    | `DashboardStats`   |

---

# 📦 4. Generate Components

```bash
php artisan make:livewire ProductManager
php artisan make:livewire SalesCreate
php artisan make:livewire SalesReport
php artisan make:livewire DashboardStats
```

---

# 🗄️ 4b. Database Schema & Migrations

### Products Table

```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->decimal('price', 12, 2);
    $table->integer('stock')->default(0);
    $table->timestamps();
});
```

### Sales Table

```php
Schema::create('sales', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('customer_name');
    $table->date('date');
    $table->decimal('total_amount', 14, 2)->default(0);
    $table->decimal('commission_amount', 14, 2)->default(0);
    $table->timestamps();
});
```

### Sale Items Table

```php
Schema::create('sale_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->integer('qty');
    $table->decimal('price', 12, 2);
    $table->decimal('subtotal', 14, 2);
    $table->timestamps();
});
```

---

# 📐 4c. Eloquent Models & Relationships

### Product Model

```php
class Product extends Model
{
    protected $fillable = ['name', 'price', 'stock'];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
    ];

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }
}
```

### Sale Model

```php
class Sale extends Model
{
    protected $fillable = [
        'user_id', 'customer_name', 'date',
        'total_amount', 'commission_amount'
    ];

    protected $casts = [
        'date' => 'date',
        'total_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }
}
```

### SaleItem Model

```php
class SaleItem extends Model
{
    protected $fillable = [
        'sale_id', 'product_id', 'qty', 'price', 'subtotal'
    ];

    protected $casts = [
        'qty' => 'integer',
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
```

---

# 📦 5. Product Module (Livewire) — Full CRUD

## Component Logic

```php
class ProductManager extends Component
{
    public $name, $price, $stock;
    public $editingId = null;

    protected $rules = [
        'name' => 'required|string|max:255',
        'price' => 'required|numeric|min:0',
        'stock' => 'required|integer|min:0',
    ];

    public function save()
    {
        $this->validate();

        Product::updateOrCreate(
            ['id' => $this->editingId],
            [
                'name' => $this->name,
                'price' => $this->price,
                'stock' => $this->stock,
            ]
        );

        $this->resetForm();
        session()->flash('success', $this->editingId ? 'Product updated!' : 'Product saved!');
    }

    public function edit($id)
    {
        $product = Product::findOrFail($id);
        $this->editingId = $product->id;
        $this->name = $product->name;
        $this->price = $product->price;
        $this->stock = $product->stock;
    }

    public function delete($id)
    {
        $product = Product::findOrFail($id);

        if ($product->saleItems()->exists()) {
            session()->flash('error', 'Cannot delete product with existing sales.');
            return;
        }

        $product->delete();
        session()->flash('success', 'Product deleted!');
    }

    public function resetForm()
    {
        $this->reset(['name', 'price', 'stock', 'editingId']);
    }

    public function render()
    {
        return view('livewire.product-manager', [
            'products' => Product::latest()->paginate(10)
        ]);
    }
}
```

---

## Blade View

```blade
<div>
    @include('partials.flash-messages')

    <input wire:model="name" placeholder="Product Name">
    <input wire:model="price" type="number">
    <input wire:model="stock" type="number">

    <button wire:click="save">{{ $editingId ? 'Update' : 'Save' }}</button>
    @if($editingId)
        <button wire:click="resetForm">Cancel</button>
    @endif

    <table>
        @foreach($products as $product)
            <tr>
                <td>{{ $product->name }}</td>
                <td>{{ number_format($product->price, 2) }}</td>
                <td>{{ $product->stock }}</td>
                <td>
                    <button wire:click="edit({{ $product->id }})">Edit</button>
                    <button wire:click="delete({{ $product->id }})"
                        wire:confirm="Are you sure?">Delete</button>
                </td>
            </tr>
        @endforeach
    </table>

    {{ $products->links() }}
</div>
```

---

# 🧾 6. Sales Form (MULTI PRODUCT — IMPORTANT)

This is the core part.

## Component

```php
class SalesCreate extends Component
{
    public $customer;
    public $items = [];

    protected $rules = [
        'customer' => 'required|string|max:255',
        'items' => 'required|array|min:1',
        'items.*.product_id' => 'required|exists:products,id',
        'items.*.qty' => 'required|integer|min:1',
    ];

    public function addItem()
    {
        $this->items[] = [
            'product_id' => '',
            'qty' => 1
        ];
    }

    public function removeItem($index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save()
    {
        $this->validate();

        try {
            app(SaleService::class)->createSale(
                auth()->user(),
                $this->customer,
                $this->items
            );

            session()->flash('success', 'Sale recorded successfully!');
            return redirect()->route('sales');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.sales-create', [
            'products' => Product::all()
        ]);
    }
}
```

---

# 🧠 7. BEST PRACTICE: Use Service Layer

👉 Don’t put logic inside Livewire

Create:

```bash
app/Services/SaleService.php
```

```php
class SaleService
{
    public function createSale($user, $customer, $items)
    {
        return DB::transaction(function () use ($user, $customer, $items) {

            $sale = Sale::create([
                'user_id' => $user->id,
                'customer_name' => $customer,
                'date' => now()
            ]);

            $total = 0;

            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);

                // Stock check
                if ($product->stock < $item['qty']) {
                    throw new \Exception("Insufficient stock for {$product->name}. Available: {$product->stock}");
                }

                $subtotal = $product->price * $item['qty'];

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'qty' => $item['qty'],
                    'price' => $product->price,
                    'subtotal' => $subtotal
                ]);

                $product->decrement('stock', $item['qty']);
                $total += $subtotal;
            }

            $commission = app(CommissionService::class)->calculate($total);

            $sale->update([
                'total_amount' => $total,
                'commission_amount' => $commission
            ]);

            return $sale;
        });
    }

    public function voidSale($saleId)
    {
        return DB::transaction(function () use ($saleId) {
            $sale = Sale::with('items')->findOrFail($saleId);

            // Restore stock for each item
            foreach ($sale->items as $item) {
                $item->product->increment('stock', $item->qty);
            }

            $sale->items()->delete();
            $sale->delete();

            return true;
        });
    }
}
```

---

# 💰 8. Commission Service (Clean Design)

```php
class CommissionService
{
    public function calculate($total)
    {
        return $total * 0.05;
    }
}
```

👉 Later you can upgrade:

- Per agent %
- Per product %

---

# 📊 9. Report Page (Livewire + Chart)

> ⚠️ **Chart.js Required** — Add the CDN in your layout before `@livewireScripts`:
>
> ```html
> <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
> ```

### Livewire Component

```php
class SalesReport extends Component
{
    public $startDate;
    public $endDate;

    public function mount()
    {
        $this->startDate = now()->startOfMonth()->toDateString();
        $this->endDate = now()->toDateString();
    }

    public function getChartDataProperty()
    {
        return Sale::selectRaw('DATE(date) as day, SUM(total_amount) as total')
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->groupBy('day')
            ->orderBy('day')
            ->get();
    }

    public function render()
    {
        return view('livewire.sales-report', [
            'sales' => Sale::with('items.product')
                ->whereBetween('date', [$this->startDate, $this->endDate])
                ->latest()
                ->paginate(15),
            'chartData' => $this->chartData,
        ]);
    }
}
```

### Blade (Chart.js)

```blade
<div>
    {{-- Date Filters --}}
    <div>
        <input type="date" wire:model.live="startDate">
        <input type="date" wire:model.live="endDate">
    </div>

    {{-- Sales Table --}}
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Customer</th>
                <th>Total</th>
                <th>Commission</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sales as $sale)
                <tr>
                    <td>{{ $sale->date }}</td>
                    <td>{{ $sale->customer_name }}</td>
                    <td>{{ number_format($sale->total_amount) }}</td>
                    <td>{{ number_format($sale->commission_amount) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $sales->links() }}

    {{-- Chart --}}
    <canvas id="salesChart"></canvas>
</div>

@script
<script>
    const chartData = $wire.chartData;

    new Chart(document.getElementById('salesChart'), {
        type: 'bar',
        data: {
            labels: chartData.map(d => d.day),
            datasets: [{
                label: 'Daily Sales',
                data: chartData.map(d => d.total),
                backgroundColor: 'rgba(59, 130, 246, 0.5)',
            }]
        }
    });
</script>
@endscript
```

---

# 📤 10. Export Button (Livewire)

### Export Class (`app/Exports/SalesExport.php`)

```php
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SalesExport implements FromQuery, WithHeadings, WithMapping
{
    protected $startDate;
    protected $endDate;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function query()
    {
        return Sale::query()
            ->with('items.product', 'user')
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->latest();
    }

    public function headings(): array
    {
        return ['ID', 'Date', 'Salesperson', 'Customer', 'Total', 'Commission'];
    }

    public function map($sale): array
    {
        return [
            $sale->id,
            $sale->date->format('Y-m-d'),
            $sale->user->name,
            $sale->customer_name,
            $sale->total_amount,
            $sale->commission_amount,
        ];
    }
}
```

### In SalesReport Component

```php
public function export()
{
    return Excel::download(
        new SalesExport($this->startDate, $this->endDate),
        'sales-report.xlsx'
    );
}
```

---

# 🔐 11. Protect with Roles

```php
$this->authorize('view reports');
```

Or:

```php
@role('admin')
    <livewire:sales-report />
@endrole
```

---

# 🧭 12. Routing

```php
Route::middleware(['auth'])->group(function () {

    Route::get('/products', ProductManager::class);
    Route::get('/sales/create', SalesCreate::class);
    Route::get('/reports', SalesReport::class);

});
```

---

# 📊 13. Dashboard Component (`DashboardStats`)

```php
class DashboardStats extends Component
{
    public function render()
    {
        $today = now()->toDateString();
        $thisMonth = now()->startOfMonth()->toDateString();

        return view('livewire.dashboard-stats', [
            'todaySales' => Sale::whereDate('date', $today)->sum('total_amount'),
            'monthlySales' => Sale::whereBetween('date', [$thisMonth, $today])->sum('total_amount'),
            'totalProducts' => Product::count(),
            'lowStockProducts' => Product::where('stock', '<=', 5)->get(),
            'topProducts' => SaleItem::selectRaw('product_id, SUM(qty) as total_qty, SUM(subtotal) as total_revenue')
                ->with('product')
                ->groupBy('product_id')
                ->orderByDesc('total_revenue')
                ->limit(5)
                ->get(),
            'recentSales' => Sale::with('user')->latest()->limit(5)->get(),
        ]);
    }
}
```

---

# 🔔 14. Flash Messages Partial

Create `resources/views/partials/flash-messages.blade.php`:

```blade
@if(session()->has('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

@if(session()->has('error'))
    <div class="alert alert-danger">
        {{ session('error') }}
    </div>
@endif
```

👉 Include in every Livewire view:

```blade
@include('partials.flash-messages')
```

---

# 🌱 15. Database Seeder

```php
// database/seeders/ProductSeeder.php
class ProductSeeder extends Seeder
{
    public function run()
    {
        $products = [
            ['name' => 'Widget A', 'price' => 25000, 'stock' => 100],
            ['name' => 'Widget B', 'price' => 50000, 'stock' => 75],
            ['name' => 'Gadget X', 'price' => 150000, 'stock' => 30],
            ['name' => 'Gadget Y', 'price' => 200000, 'stock' => 20],
            ['name' => 'Tool Z', 'price' => 75000, 'stock' => 50],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}
```

```bash
php artisan db:seed --class=ProductSeeder
```

---

# 🔥 16. My Advice (Important)

Avoid this mistake:
❌ Putting logic in Livewire
❌ Mixing API & UI logic

Always:
✔ Use Service classes
✔ Keep Livewire thin
