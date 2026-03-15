<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'discount_price' => $this->discount_price,
            // Handle: base64 data URL | full http URL | storage path
            'image_url' => $this->image
                ? (str_starts_with($this->image, 'data:') || str_starts_with($this->image, 'http')
                    ? $this->image
                    : asset('storage/' . $this->image))
                : null,
            'stock' => $this->stock,
            'is_available' => (bool) $this->is_available,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'avg_rating' => (float) ($this->reviews->avg('rating') ?? 4.5), // Fallback for UI polish
            'review_count' => $this->reviews->count(),
            'created_at' => $this->created_at,
        ];
    }
}
