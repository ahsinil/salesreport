<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\CommissionSettingUpdateRequest;
use App\Http\Resources\CommissionSettingResource;
use App\Services\CommissionService;
use Illuminate\Http\JsonResponse;

class CommissionSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => new CommissionSettingResource(app(CommissionService::class)->settings()),
        ]);
    }

    public function update(CommissionSettingUpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $commissionSettings = app(CommissionService::class)->settings();

        $commissionSettings->update([
            'basis' => $validated['basis'],
            'default_rate' => $validated['default_rate'],
        ]);

        return response()->json([
            'message' => 'Pengaturan komisi berhasil diperbarui.',
            'data' => new CommissionSettingResource($commissionSettings->fresh()),
        ]);
    }
}
