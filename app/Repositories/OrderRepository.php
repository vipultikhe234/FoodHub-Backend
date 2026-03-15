<?php

namespace App\Repositories;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

class OrderRepository
{
    public function getAll()
    {
        return Order::with(['user', 'items.product', 'payment', 'coupon'])->latest()->get();
    }

    public function findById($id)
    {
        return Order::with(['user', 'items.product', 'payment', 'coupon'])->find($id);
    }

    public function getUserOrders($userId)
    {
        return Order::with(['items.product', 'coupon'])->where('user_id', $userId)->latest()->get();
    }

    public function create(array $data, array $items)
    {
        return DB::transaction(function () use ($data, $items) {
            $order = Order::create($data);

            $orderItems = array_map(function ($item) use ($order) {
                return [
                    'order_id'   => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                    'price'      => $item['price'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }, $items);

            OrderItem::insert($orderItems);

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
