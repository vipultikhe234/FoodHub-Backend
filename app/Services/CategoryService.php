<?php

namespace App\Services;

use App\Repositories\CategoryRepository;

class CategoryService
{
    public function __construct(
        protected CategoryRepository $repository
    ) {}

    public function getAllCategories()
    {
        return $this->repository->getAll();
    }

    public function getCategoryById(int $id)
    {
        return $this->repository->findById($id);
    }

    public function createCategory(array $data)
    {
        return $this->repository->create($data);
    }

    public function updateCategory(int $id, array $data)
    {
        return $this->repository->update($id, $data);
    }

    public function deleteCategory(int $id)
    {
        return $this->repository->delete($id);
    }
}
