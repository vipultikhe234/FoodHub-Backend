<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'user'        => [
                'name'    => $this->user?->name ?? 'Anonymous User',
            ],
            'merchant'    => [
                'id'      => $this->merchant_id,
                'name'    => $this->merchant?->name ?? 'Deleted Merchant',
            ],
            'order_id'    => $this->order_id,
            'rating'      => (int) $this->rating,
            'review'      => $this->review,
            'created_at'  => $this->created_at?->format('d M Y'),
        ];
    }
}
