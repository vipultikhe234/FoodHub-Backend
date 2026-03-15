<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_name' => $this->user->name,
            'rating' => (int) $this->rating,
            'comment' => $this->comment,
            'created_at' => $this->created_at->diffForHumans(),
        ];
    }
}
