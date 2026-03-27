<?php

namespace App\Repositories;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

class OrderRepository
{
    public function getAll($MerchantId = null)
    {
        return Order::byMerchant($MerchantId)
            ->with(['user', 'Merchant', 'rider', 'items.product', 'payment', 'coupon'])
            ->latest()
            ->get();
    }

    public function findById($id)
    {
        return Order::with(['user', 'Merchant', 'rider', 'items.product', 'payment', 'coupon'])->find($id);
    }

    public function getUserOrders($userId)
    {
        return Order::with(['Merchant', 'items.product', 'coupon'])->where('user_id', $userId)->latest()->get();
    }

    public function create(array $data, array $items)
    {
        return DB::transaction(function () use ($data, $items) {
            // 1. Create the Main Order Header
            $order = Order::create($data);

            foreach ($items as $item) {
                // 2. Resolve Product & Variant Context
                $product = \App\Models\Product::find($item['product_id']);
                $variant = isset($item['product_variant_id']) ? \App\Models\ProductVariant::find($item['product_variant_id']) : null;

                if (!$product) continue;

                // 3. Create Immutable Order ItemSnapshot
                $orderItem = new OrderItem([
                    'order_id'           => $order->id,
                    'product_id'         => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'product_name'       => $product->name,
                    'variant_name'       => $variant ? $variant->name : null,
                    'quantity'           => $item['quantity'],
                    'unit_price'         => $item['price'],
                    'total_price'        => $item['price'] * $item['quantity'],
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);

                $orderItem->save();

                // 4. Mission-Critical Stock Deduction
                if ($variant) {
                    // Variants use the Inventory ecosystem (Multi-Outlet)
                    $inventory = \App\Models\Inventory::where('product_variant_id', $variant->id)
                        ->where('merchant_id', $order->merchant_id)
                        ->first();
                    
                    if ($inventory) {
                        $inventory->decrement('stock', $item['quantity']);
                    }
                } else {
                    // Simple products use root stock
                    $product->decrement('stock', $item['quantity']);
                }
            }

            return $order;
        });
    }

    public function updateStatus($id, $status)
    {
        $order = Order::find($id);
        if ($order) {
            $order->update(['status' => $status]);
            return $order;
        }
        return null;
    }
}

