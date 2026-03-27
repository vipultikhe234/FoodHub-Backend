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
            'user_name'   => $this->whenLoaded('user', fn() => $this->user->name, 'Anonymous'),
            'merchant_id' => $this->merchant_id,
            'order_id'    => $this->order_id,
            'rating'      => (int) $this->rating,
            'review'      => $this->review,
            'created_at'  => $this->created_at?->diffForHumans(),
        ];
    }
}
