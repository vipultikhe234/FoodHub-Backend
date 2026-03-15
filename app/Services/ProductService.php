<?php

namespace App\Services;

use App\Repositories\ProductRepository;

class ProductService
{
    public function __construct(
        protected ProductRepository $repository
    ) {}

    public function getAllProducts()
    {
        return $this->repository->getAll();
    }

    public function getProductById(int $id)
    {
        return $this->repository->findById($id);
    }

    public function createProduct(array $data)
    {
        return $this->repository->create($data);
    }

    public function updateProduct(int $id, array $data)
    {
        return $this->repository->update($id, $data);
    }

    public function deleteProduct(int $id)
    {
        return $this->repository->delete($id);
    }
}
