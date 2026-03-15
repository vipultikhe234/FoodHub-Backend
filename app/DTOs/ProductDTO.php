<?php

namespace App\DTOs;

class ProductDTO
{
    public function __construct(
        public readonly int $category_id,
        public readonly string $name,
        public readonly float $price,
        public readonly ?string $description = null,
        public readonly ?float $discount_price = null,
        public readonly ?string $image = null,
        public readonly int $stock = 0,
        public readonly bool $is_available = true
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            category_id: $data['category_id'],
            name: $data['name'],
            price: $data['price'],
            description: $data['description'] ?? null,
            discount_price: $data['discount_price'] ?? null,
            image: $data['image'] ?? null,
            stock: $data['stock'] ?? 0,
            is_available: $data['is_available'] ?? true
        );
    }
}
