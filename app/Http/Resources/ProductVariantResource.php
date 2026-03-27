<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Calculate available stock across all relevant inventory nodes or current shop
        $MerchantId = $request->query('merchant_id');
        $inventory = $this->inventories
            ->where('merchant_id', $MerchantId)
            ->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'mrp_price' => (float) $this->mrp_price,
            'price' => (float) $this->price,
            'is_active' => (bool) $this->is_active,
            'stock' => $inventory ? $inventory->stock : null,
            'available_stock' => $inventory ? ($inventory->stock - $inventory->reserved_stock) : 0,
            'is_in_stock' => $inventory ? (($inventory->stock - $inventory->reserved_stock) > 0) : false,
        ];
    }
}

