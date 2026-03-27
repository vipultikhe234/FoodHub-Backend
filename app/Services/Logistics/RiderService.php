<?php

namespace App\Services\Logistics;

use App\Models\Order;
use App\Models\User;
use App\Services\Identity\FCMService;
use Exception;
use Illuminate\Support\Facades\DB;

class RiderService
{
    public function __construct(
        protected FCMService $fcmService
    ) {}

    public function getAvailableOrders()
    {
        // Orders that are 'ready' and don't have a rider assigned yet
        return Order::where('status', Order::STATUS_READY)
            ->whereNull('rider_id')
            ->where('order_type', Order::TYPE_DELIVERY)
            ->with(['Merchant', 'user'])
            ->get();
    }

    public function acceptOrder($riderId, $orderId)
    {
        $rider = User::find($riderId);
        if (!$rider || !$rider->isRider()) {
            throw new Exception('User is not a rider.');
        }

        $order = Order::find($orderId);
        if (!$order) {
            throw new Exception('Order not found.');
        }

        if ($order->rider_id) {
            throw new Exception('Order already assigned to another rider.');
        }

        if ($order->status !== Order::STATUS_READY) {
            throw new Exception('Order is not ready for delivery yet.');
        }

        return DB::transaction(function () use ($order, $rider) {
            $order->update([
                'rider_id' => $rider->id,
                'status'   => Order::STATUS_OUT_FOR_DELIVERY
            ]);

            // Notify user
            if ($order->user && $order->user->fcm_token) {
                $this->fcmService->sendNotification(
                    $order->user->fcm_token,
                    'Order Out for Delivery! 🛵',
                    'Rider ' . $rider->name . ' has picked up your order and is on the way!',
                    ['type' => 'order', 'id' => (string)$order->id, 'status' => Order::STATUS_OUT_FOR_DELIVERY],
                    $order->user_id
                );
            }

            return $order;
        });
    }

    public function updateRiderLocation($riderId, $lat, $lng)
    {
        $rider = User::find($riderId);
        if ($rider && $rider->isRider()) {
            $rider->update([
                'current_latitude' => $lat,
                'current_longitude' => $lng
            ]);
            return true;
        }
        return false;
    }

    public function completeDelivery($riderId, $orderId)
    {
        $order = Order::where('id', $orderId)
            ->where('rider_id', $riderId)
            ->first();

        if (!$order) {
            throw new Exception('Order not found or not assigned to this rider.');
        }

        return DB::transaction(function () use ($order) {
            $order->update([
                'status' => Order::STATUS_DELIVERED,
                'actual_delivery_time' => now()
            ]);

            // Notify user
            if ($order->user && $order->user->fcm_token) {
                $this->fcmService->sendNotification(
                    $order->user->fcm_token,
                    'Order Delivered! 🍽️',
                    'Your order has been delivered successfully. Enjoy your meal!',
                    ['type' => 'order', 'id' => (string)$order->id, 'status' => Order::STATUS_DELIVERED],
                    $order->user_id
                );
            }

            return $order;
        });
    }

    public function getRiderHistory($riderId)
    {
        return Order::where('rider_id', $riderId)
            ->whereIn('status', [Order::STATUS_DELIVERED, Order::STATUS_CANCELLED])
            ->with(['Merchant', 'user'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}

