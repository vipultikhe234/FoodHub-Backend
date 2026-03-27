<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $v = $this->whenLoaded('variants');
        $startingPrice = $this->has_variants && $v && count($v) > 0
            ? (float) collect($v)->min('price')
            : (float) $this->price;

        $discountPercentage = 0;
        if ($this->price > 0 && $this->discount_price > 0 && $this->discount_price < $this->price) {
            $discountPercentage = round((($this->price - $this->discount_price) / $this->price) * 100);
        }

        return [
            'id' => $this->id,
            'merchant_id' => $this->merchant_id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'description' => $this->description,
            'has_variants' => (bool) $this->has_variants,
            'price' => (float) $this->price,
            'discount_price' => (float) $this->discount_price,
            'starting_price' => $startingPrice,
            'discount_percentage' => $discountPercentage,
            'is_best_seller' => (int) ($this->sales_volume ?? 0) > 0, // Flag if sold at least once for this curated list
            'image_url' => $this->image
                ? (str_starts_with($this->image, 'data:') || str_starts_with($this->image, 'http')
                    ? $this->image
                    : asset('storage/' . $this->image))
                : null,
            'stock' => (int) $this->stock,
            'is_available' => (bool) $this->is_available,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'merchant' => $this->whenLoaded('merchant'),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'avg_rating' => (float) ($this->reviews->avg('rating') ?? 4.5), 
            'review_count' => $this->reviews->count(),
            'created_at' => $this->created_at,
        ];
    }
}

