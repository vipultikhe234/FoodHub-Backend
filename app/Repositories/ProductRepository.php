<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository
{
    public function getAll($merchantId = null, $cityId = null): Collection
    {
        // RELAXED FILTER: Show ALL products for the merchant, even those in 'draft' categories
        $query = Product::query()
            ->when($merchantId, function($q) use ($merchantId) {
                $q->where('merchant_id', $merchantId);
            })
            ->with(['category', 'merchant.merchantCategory', 'merchant.other_charges', 'variants.inventories'])
            ->latest();

        if ($cityId) {
            $query->whereHas('merchant', function ($q) use ($cityId) {
                $q->where('city_id', $cityId);
            });
        }

        return $query->get();
    }

    public function findById(int $id): ?Product
    {
        return Product::with(['category', 'merchant.merchantCategory', 'merchant.other_charges', 'reviews.user', 'variants.inventories'])
            ->find($id);
    }

    public function create(array $data): Product
    {
        return \Illuminate\Support\Facades\DB::transaction(function() use ($data) {
            $product = Product::create($data);
            
            if (!empty($data['variants']) && is_array($data['variants'])) {
                foreach ($data['variants'] as $vData) {
                    $variant = $product->variants()->create($vData);
                    
                    // Sync with Inventory for the owner Merchant
                    \App\Models\Inventory::create([
                        'product_variant_id' => $variant->id,
                        'merchant_id'        => $product->merchant_id,
                        'stock'               => $vData['stock'] ?? 0,
                        'reserved_stock'      => 0,
                        'is_available'        => true
                    ]);
                }
                
                // Update product total stock summary for global listings
                $totalStock = collect($data['variants'])->sum('stock');
                $product->update(['stock' => $totalStock]);
            }
            return $product;
        });
    }

    public function update(int $id, array $data): bool
    {
        $product = Product::find($id);
        if (!$product) {
            return false;
        }

        return \Illuminate\Support\Facades\DB::transaction(function() use ($product, $data) {
            $product->update($data);

            if (isset($data['variants']) && is_array($data['variants'])) {
                $newVariantIds = [];
                foreach ($data['variants'] as $vData) {
                    $variant = null;
                    if (isset($vData['id'])) {
                        $variant = \App\Models\ProductVariant::find($vData['id']);
                        if ($variant) {
                            $variant->update($vData);
                        }
                    }
                    
                    if (!$variant) {
                        $variant = $product->variants()->create($vData);
                    }

                    $newVariantIds[] = $variant->id;

                    // Sync Inventory for the merchant
                    \App\Models\Inventory::updateOrCreate(
                        [
                            'product_variant_id' => $variant->id, 
                            'merchant_id' => $product->merchant_id
                        ],
                        [
                            'stock' => $vData['stock'] ?? 0,
                            'is_available' => true
                        ]
                    );
                }

                // Cleanup variants removed in frontend
                $product->variants()->whereNotIn('id', $newVariantIds)->delete();

                // Update product total stock summary
                $totalStock = \App\Models\Inventory::whereIn('product_variant_id', $newVariantIds)
                    ->where('merchant_id', $product->merchant_id)
                    ->sum('stock');
                $product->update(['stock' => $totalStock]);
            }

            return true;
        });
    }

    public function delete(int $id): bool
    {
        return Product::destroy($id) > 0;
    }

    public function getCuratedProducts($cityId = null): Collection
    {
        // 1. Discounted Products (Active, In-Stock, with Discount)
        $discountedQuery = Product::active()
            ->where('is_available', true)
            ->whereNotNull('discount_price')
            ->where('discount_price', '>', 0)
            ->whereColumn('discount_price', '<', 'price')
            ->selectRaw('*, (price - discount_price) as discount_value')
            ->orderBy('discount_value', 'desc')
            ->with(['category', 'merchant.merchantCategory', 'merchant.other_charges', 'variants.inventories', 'reviews'])
            ->limit(10);

        if ($cityId) {
            $discountedQuery->whereHas('merchant', function ($q) use ($cityId) {
                $q->where('city_id', $cityId);
            });
        }

        $discounted = $discountedQuery->get();

        // 2. Most Selling Products (Historical Popularity)
        $popularQuery = Product::active()
            ->where('is_available', true)
            ->leftJoin('order_items', 'products.id', '=', 'order_items.product_id')
            ->selectRaw('products.*, SUM(COALESCE(order_items.quantity, 0)) as sales_volume')
            ->groupBy('products.id')
            ->orderBy('sales_volume', 'desc')
            ->with(['category', 'merchant.merchantCategory', 'merchant.other_charges', 'variants.inventories', 'reviews'])
            ->limit(10);

        if ($cityId) {
            $popularQuery->whereHas('merchant', function ($q) use ($cityId) {
                $q->where('city_id', $cityId);
            });
        }

        $popular = $popularQuery->get();

        $merged = $discounted->concat($popular)
            ->unique('id')
            ->take(10);

        // 3. Resilient Fallback: If no high-priority matches, fill with latest active inventory
        if ($merged->count() < 5) {
            $latestQuery = Product::active()
                ->where('is_available', true)
                ->with(['category', 'merchant.merchantCategory', 'merchant.other_charges', 'variants.inventories', 'reviews'])
                ->latest()
                ->limit(10);
            
            if ($cityId) {
                $latestQuery->whereHas('merchant', function ($q) use ($cityId) {
                    $q->where('city_id', $cityId);
                });
            }

            $latest = $latestQuery->get();
            
            $merged = $merged->concat($latest)->unique('id')->take(10);
        }

        return $merged;
    }
}

