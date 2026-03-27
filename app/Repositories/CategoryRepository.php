<?php

namespace App\Repositories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

class CategoryRepository
{
    public function getAll($MerchantId = null): Collection
    {
        return Category::byMerchant($MerchantId)->latest()->get();
    }

    public function findById(int $id): ?Category
    {
        return Category::find($id);
    }

    public function create(array $data): Category
    {
        return Category::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $category = Category::find($id);
        if (!$category) {
            return false;
        }
        return $category->update($data);
    }

    public function delete(int $id): bool
    {
        return Category::destroy($id) > 0;
    }
}

