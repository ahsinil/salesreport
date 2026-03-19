<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\SalesPermissionUpdateRequest;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SalesPermissionController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => $this->permissionPayload(),
        ]);
    }

    public function update(SalesPermissionUpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $salesRole = $this->ensureRoleAndPermissions('sales');
        $salesRole->syncPermissions($validated['permissions'] ?? []);

        return response()->json([
            'message' => 'Hak akses sales berhasil diperbarui.',
            'data' => $this->permissionPayload(),
        ]);
    }

    public function resetSales(): JsonResponse
    {
        $salesRole = $this->ensureRoleAndPermissions('sales');
        $salesRole->syncPermissions(Permissions::salesDefaults());

        return response()->json([
            'message' => 'Hak akses sales dikembalikan ke bawaan.',
            'data' => $this->permissionPayload(),
        ]);
    }

    public function resetAdmin(): JsonResponse
    {
        $adminRole = $this->ensureRoleAndPermissions('admin');
        $adminRole->syncPermissions(Permissions::all());

        return response()->json([
            'message' => 'Hak akses admin dikembalikan ke bawaan.',
            'data' => $this->permissionPayload(),
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function permissionPayload(): array
    {
        $salesRole = $this->ensureRoleAndPermissions('sales');
        $adminRole = $this->ensureRoleAndPermissions('admin');
        $salesRole->loadMissing('permissions');
        $adminRole->loadMissing('permissions');
        $salesGrantedPermissions = $salesRole->permissions->pluck('name')->values()->all();

        return [
            'sales' => [
                'available_permissions' => collect(Permissions::salesOptions())
                    ->map(function (array $permission, string $name) use ($salesGrantedPermissions): array {
                        return [
                            'name' => $name,
                            'label' => $permission['label'],
                            'description' => $permission['description'],
                            'enabled' => in_array($name, $salesGrantedPermissions, true),
                        ];
                    })
                    ->values()
                    ->all(),
                'granted_permissions' => $salesGrantedPermissions,
                'default_permissions' => Permissions::salesDefaults(),
            ],
            'admin' => [
                'granted_permissions' => $adminRole->permissions->pluck('name')->values()->all(),
                'default_permissions' => Permissions::all(),
            ],
        ];
    }

    protected function ensureRoleAndPermissions(string $roleName): Role
    {
        foreach (Permissions::all() as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        return Role::findOrCreate($roleName, 'web');
    }
}
