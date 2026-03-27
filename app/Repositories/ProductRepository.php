<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository
{
    public function getAll($merchantId = null): Collection
    {
        return Product::byMerchant($merchantId)
            ->active()
            ->with(['category', 'merchant', 'variants.inventories'])
            ->latest()
            ->get();
    }

    public function findById(int $id): ?Product
    {
        return Product::with(['category', 'merchant', 'reviews.user', 'variants.inventories'])
            ->find($id);
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

    public function getCuratedProducts(): Collection
    {
        // 1. Discounted Products (Active, In-Stock, with Discount)
        $discounted = Product::active()
            ->where('is_available', true)
            ->where(function($q) {
                // If stock exists, check it, otherwise ignore for dev flexibility
                $q->where('stock', '>', 0)->orWhere('stock', 0); 
            })
            ->whereNotNull('discount_price')
            ->where('discount_price', '>', 0)
            ->whereColumn('discount_price', '<', 'price')
            ->selectRaw('*, (price - discount_price) as discount_value')
            ->orderBy('discount_value', 'desc')
            ->with(['category', 'merchant', 'variants.inventories', 'reviews'])
            ->limit(10)
            ->get();

        // 2. Most Selling Products (Historical Popularity)
        $popular = Product::active()
            ->where('is_available', true)
            ->leftJoin('order_items', 'products.id', '=', 'order_items.product_id')
            ->selectRaw('products.*, SUM(COALESCE(order_items.quantity, 0)) as sales_volume')
            ->groupBy('products.id')
            ->orderBy('sales_volume', 'desc')
            ->with(['category', 'merchant', 'variants.inventories', 'reviews'])
            ->limit(10)
            ->get();

        $merged = $discounted->concat($popular)
            ->unique('id')
            ->take(10);

        // 3. Resilient Fallback: If no high-priority matches, fill with latest active inventory
        if ($merged->count() < 5) {
            $latest = Product::active()
                ->where('is_available', true)
                ->with(['category', 'merchant', 'variants.inventories', 'reviews'])
                ->latest()
                ->limit(10)
                ->get();
            
            $merged = $merged->concat($latest)->unique('id')->take(10);
        }

        return $merged;
    }
}

