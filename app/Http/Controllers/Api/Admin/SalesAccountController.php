<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\SalesAccountIndexRequest;
use App\Http\Requests\Api\Admin\SalesAccountStoreRequest;
use App\Http\Requests\Api\Admin\SalesAccountUpdateRequest;
use App\Http\Resources\SalesAccountResource;
use App\Models\User;
use App\Services\CommissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class SalesAccountController extends Controller
{
    public function index(SalesAccountIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $defaultCommissionRate = app(CommissionService::class)->defaultRate();
        $salesAccountsQuery = User::query()
            ->role('sales')
            ->withCount('sales')
            ->withMax('sales', 'date')
            ->when(($validated['search'] ?? '') !== '', function ($query) use ($validated): void {
                $searchTerm = '%'.$validated['search'].'%';

                $query->where(function ($searchQuery) use ($searchTerm): void {
                    $searchQuery
                        ->whereLike('name', $searchTerm)
                        ->orWhereLike('email', $searchTerm);
                });
            });

        if ($validated['sort_by'] === 'commission_rate') {
            $salesAccountsQuery
                ->orderByRaw('COALESCE(commission_rate, ?) '.$validated['sort_direction'], [$defaultCommissionRate])
                ->orderBy('id');
        } else {
            $salesAccountsQuery
                ->orderBy($validated['sort_by'], $validated['sort_direction'])
                ->when($validated['sort_by'] !== 'id', function ($query): void {
                    $query->orderBy('id');
                });
        }

        $salesAccounts = $salesAccountsQuery->paginate($validated['per_page']);

        return response()->json([
            'summary' => [
                'sales_account_count' => User::query()->role('sales')->count(),
                'active_sales_account_count' => User::query()->role('sales')->whereHas('sales')->count(),
                'pending_sales_account_count' => User::query()->role('sales')->where('is_approved', false)->count(),
                'default_commission_rate' => $defaultCommissionRate,
            ],
            'sales_accounts' => SalesAccountResource::collection($salesAccounts)->toResponse($request)->getData(true),
        ]);
    }

    public function store(SalesAccountStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->ensureSalesRole();

        $salesUser = DB::transaction(function () use ($validated): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'is_approved' => true,
                'commission_rate' => $validated['commission_rate'] ?? null,
            ]);

            $user->syncRoles('sales');

            return $user;
        });

        return response()->json([
            'message' => 'Akun sales berhasil dibuat.',
            'data' => new SalesAccountResource($salesUser->loadCount('sales')->loadMax('sales', 'date')),
        ], 201);
    }

    public function show(User $salesAccount): SalesAccountResource
    {
        return new SalesAccountResource($this->resolveSalesAccount($salesAccount)->loadCount('sales')->loadMax('sales', 'date'));
    }

    public function update(SalesAccountUpdateRequest $request, User $salesAccount): JsonResponse
    {
        $validated = $request->validated();
        $salesUser = $this->resolveSalesAccount($salesAccount);

        DB::transaction(function () use ($salesUser, $validated): void {
            $attributes = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'commission_rate' => $validated['commission_rate'] ?? null,
            ];

            if (filled($validated['password'] ?? null)) {
                $attributes['password'] = $validated['password'];
            }

            $salesUser->update($attributes);
            $salesUser->syncRoles('sales');
        });

        return response()->json([
            'message' => 'Akun sales berhasil diperbarui.',
            'data' => new SalesAccountResource($salesUser->fresh()->loadCount('sales')->loadMax('sales', 'date')),
        ]);
    }

    public function destroy(User $salesAccount): JsonResponse
    {
        $salesUser = $this->resolveSalesAccount($salesAccount)->loadCount('sales');

        if ($salesUser->sales_count > 0) {
            return response()->json([
                'message' => 'Akun sales yang sudah memiliki transaksi tidak dapat dihapus.',
            ], 422);
        }

        $salesUser->delete();

        return response()->json([
            'message' => 'Akun sales berhasil dihapus.',
        ]);
    }

    public function approve(User $salesAccount): JsonResponse
    {
        $salesUser = $this->resolveSalesAccount($salesAccount);
        $salesUser->update([
            'is_approved' => true,
        ]);

        return response()->json([
            'message' => 'Akun sales berhasil disetujui.',
            'data' => new SalesAccountResource($salesUser->fresh()->loadCount('sales')->loadMax('sales', 'date')),
        ]);
    }

    protected function ensureSalesRole(): void
    {
        Role::findOrCreate('sales', 'web');
    }

    protected function resolveSalesAccount(User $salesAccount): User
    {
        return User::query()
            ->role('sales')
            ->findOrFail($salesAccount->id);
    }
}
