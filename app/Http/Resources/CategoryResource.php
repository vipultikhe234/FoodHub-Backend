<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // Handle: base64 data URL | full http URL | storage path
            'image_url' => $this->image
                ? (str_starts_with($this->image, 'data:') || str_starts_with($this->image, 'http')
                    ? $this->image
                    : asset('storage/' . $this->image))
                : null,
            'status' => (bool) $this->status,
            'merchant_id' => $this->merchant_id,
            'merchant' => $this->whenLoaded('merchant'),
        ];
    }
}

