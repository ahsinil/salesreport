<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesAccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_approved' => (bool) $this->is_approved,
            'commission_rate' => $this->commission_rate !== null ? (float) $this->commission_rate : null,
            'sales_count' => $this->sales_count !== null ? (int) $this->sales_count : null,
            'last_sale_date' => $this->sales_max_date !== null ? \Illuminate\Support\Carbon::parse($this->sales_max_date)->toDateString() : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
