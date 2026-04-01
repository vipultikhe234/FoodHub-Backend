<?php

namespace App\Services\Inventory;

use App\Repositories\ProductRepository;

class ProductService
{
    public function __construct(
        protected ProductRepository $repository
    ) {}

    public function getAllProducts($merchantId = null, $cityId = null)
    {
        return $this->repository->getAll($merchantId, $cityId);
    }

    public function getProductById($id)
    {
        return $this->repository->findById((int) $id);
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

    public function getCuratedProducts($cityId = null)
    {
        return $this->repository->getCuratedProducts($cityId);
    }
}

