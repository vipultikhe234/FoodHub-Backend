<?php

namespace App\Services;

use App\Repositories\OrderRepository;
use App\Services\StripePaymentService;
// use App\Events\OrderStatusChanged; // Uncomment when websockets are set up
use Exception;

class OrderService
{
    public function __construct(
        protected OrderRepository $repository,
        protected StripePaymentService $stripeService,
        protected \App\Services\FCMService $fcmService
    ) {}

    public function placeOrder(array $data, array $items)
    {
        // 0. If Stripe selected, validate API key exists BEFORE creating order
        if ($data['payment_method'] === 'stripe') {
            $stripeSecret = config('services.stripe.secret');
            if (empty($stripeSecret)) {
                throw new \Exception(
                    'Stripe payment is not configured on this server. Please use Cash on Delivery or contact support.'
                );
            }
        }

        // 1. Calculate base total from items
        $deliveryFee = 49.00;
        $subtotal = array_reduce($items, function ($carry, $item) {
            return $carry + ($item['price'] * $item['quantity']);
        }, 0);

        $discount = 0;
        $couponId = null;

        // 2. Handle Coupon if provided
        if (!empty($data['coupon_code'])) {
            $coupon = \App\Models\Coupon::where('code', $data['coupon_code'])
                ->where('is_active', true)
                ->where('expires_at', '>', now())
                ->first();

            if (!$coupon) {
                throw new \Exception('The applied coupon is invalid or has expired.');
            }

            if ($subtotal < $coupon->min_order_amount) {
                throw new \Exception('Minimum order amount for this coupon is ₹' . $coupon->min_order_amount);
            }

            if ($coupon->type === 'percentage') {
                $discount = ($subtotal * $coupon->value) / 100;
            } else {
                $discount = $coupon->value;
            }
            $couponId = $coupon->id;
        }

        $totalPrice = ($subtotal + $deliveryFee) - $discount;
        if ($totalPrice < 0) $totalPrice = 0;

        // 3. Wrap order + payment creation in a transaction
        return \DB::transaction(function () use ($data, $items, $totalPrice, $discount, $couponId) {
            $orderData = [
                'user_id'        => $data['user_id'],
                'total_price'    => $totalPrice,
                'status'         => 'pending',
                'payment_status' => 'pending',
                'address'        => $data['delivery_address'],
                'discount'       => $discount,
                'coupon_id'      => $couponId,
            ];

            $order = $this->repository->create($orderData, $items);
            $order->load(['user']);

            $order->payment()->create([
                'payment_method' => $data['payment_method'],
                'amount'         => $totalPrice,
                'status'         => 'pending',
            ]);

            // Notify user of order success
            if ($order->user && $order->user->fcm_token) {
                $this->fcmService->sendNotification(
                    $order->user->fcm_token,
                    'Order Confirmed! 🎉',
                    'Your order ID #' . str_pad($order->id, 4, '0', STR_PAD_LEFT) . ' has been placed successfully.',
                    ['type' => 'order', 'id' => (string)$order->id],
                    $order->user_id
                );
            }

            // 4. Stripe PaymentIntent (only if method is stripe)
            if ($data['payment_method'] === 'stripe') {
                $intent = $this->stripeService->createPaymentIntent($order);
                if (!$intent['success']) {
                    throw new \Exception('Stripe interaction failed: ' . $intent['message']);
                }
                return [
                    'order'               => $order,
                    'stripe_client_secret' => $intent['client_secret'],
                ];
            }

            return ['order' => $order];
        });
    }

    public function updateOrderStatus($id, $status)
    {
        $order = $this->repository->findById($id);
        if (!$order) return null;

        // Force reload the model and its relations to prevent stale data checks
        $order->refresh();
        $order->load(['payment']);

        // Requirement: COD orders must be Paid before becoming Delivered
        if ($status === 'delivered') {
            $paymentMethod = $order->payment?->payment_method;
            if ($paymentMethod === 'cod' && $order->payment_status !== 'paid') {
                throw new \Exception('Cash on Delivery orders must be marked as PAID before setting status to DELIVERED.');
            }
        }

        $order = $this->repository->updateStatus($id, $status);
        if ($order) {
            $order->load(['user']); // Ensure user info is loaded for notification
            // Notify user of status change
            if ($order->user && $order->user->fcm_token) {
                $statusMsg = match($status) {
                    'preparing'  => 'Your order is being prepared by the kitchen! 🧑‍🍳',
                    'dispatched' => 'Your order is on the way! 🛵💨',
                    'delivered'  => 'Your order has been delivered! Enjoy your meal! 🍽️',
                    'cancelled'  => 'Your order has been cancelled.',
                    default      => 'Your order status has been updated to: ' . ucfirst($status)
                };

                $this->fcmService->sendNotification(
                    $order->user->fcm_token,
                    'Order Update',
                    $statusMsg,
                    ['type' => 'order', 'id' => (string)$order->id, 'status' => $status],
                    $order->user_id
                );
            }

            // event(new OrderStatusChanged($order, $status)); // Re-enable with websockets
            return $order;
        }
        return null;
    }

    public function updatePaymentStatus($id, $paymentStatus)
    {
        $order = $this->repository->findById($id);
        if (!$order) return null;

        return \DB::transaction(function () use ($order, $paymentStatus) {
            $order->update(['payment_status' => $paymentStatus]);
            
            if ($order->payment) {
                // Map status to match the payments table enum: ['pending', 'completed', 'failed']
                $mappedStatus = match($paymentStatus) {
                    'paid'      => 'completed',
                    'failed'    => 'failed',
                    default     => 'pending',
                };
                $order->payment->update(['status' => $mappedStatus]);
            }

            return $order;
        });
    }

    public function getOrders()
    {
        return $this->repository->getAll();
    }

    public function getUserOrders($userId)
    {
        return $this->repository->getUserOrders($userId);
    }

    public function initiatePayment($id)
    {
        $order = $this->repository->findById($id);
        if (!$order) {
            throw new \Exception('Order not found');
        }

        $result = $this->stripeService->createPaymentIntent($order);
        if (!$result['success']) {
            throw new \Exception('Stripe creation failed: ' . $result['message']);
        }

        return $result;
    }
}
