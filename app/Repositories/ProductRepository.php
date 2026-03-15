<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository
{
    public function getAll(): Collection
    {
        return Product::with('category')->get();
    }

    public function findById(int $id): ?Product
    {
        return Product::with(['category', 'reviews.user'])->find($id);
    }

    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $product = Product::find($id);
        if (!$product) {
            return false;
        }
        return $product->update($data);
    }

    public function delete(int $id): bool
    {
        return Product::destroy($id) > 0;
    }
}
