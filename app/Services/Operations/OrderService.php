<?php

namespace App\Services\Operations;

use App\Repositories\OrderRepository;
use App\Services\Gateways\StripePaymentService;
use App\Services\Identity\FCMService;
use Exception;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        protected OrderRepository $repository,
        protected StripePaymentService $stripeService,
        protected FCMService $fcmService
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

        // 1. Resolve Merchant Context (From first item)
        $firstItemProduct = \App\Models\Product::find($items[0]['product_id'] ?? null);
        $MerchantId = $firstItemProduct ? $firstItemProduct->merchant_id : null;
        $Merchant = $MerchantId ? \App\Models\Merchant::with('otherCharges')->find($MerchantId) : null;
        $charges = $Merchant ? $Merchant->otherCharges : null;

        // 2. Base Financial Parameters
        $orderType = $data['order_type'] ?? \App\Models\Order::TYPE_DELIVERY;
        $deliveryFee = 0.00;
        $distance = 0.00;
        
        if ($orderType !== \App\Models\Order::TYPE_PICKUP && $charges) {
            if ($charges->delivery_charge_type === 'distance') {
                $userLat = $data['latitude'] ?? null;
                $userLng = $data['longitude'] ?? null;
                $restLat = $Merchant->latitude;
                $restLng = $Merchant->longitude;

                if ($userLat && $userLng && $restLat && $restLng) {
                    $distance = \App\Services\Logistics\DistanceService::calculateDistance($userLat, $userLng, $restLat, $restLng);

                    if ($charges->max_delivery_distance > 0 && $distance > $charges->max_delivery_distance) {
                        throw new \Exception("The delivery location is beyond the merchant's service radius (Distance: {$distance}km, Limit: {$charges->max_delivery_distance}km).");
                    }

                    $deliveryFee = $distance * ($charges->delivery_charge_per_km ?? 0.00);
                } else {
                    // Fallback to fixed charge if coordinates missing
                    $deliveryFee = $charges->delivery_charge ?? 0.00;
                }
            } else {
                $deliveryFee = $charges->delivery_charge ?? 0.00;
            }
        }

        $packagingCharge = $charges->packaging_charge ?? 0.00;
        $platformFee = $charges->platform_fee ?? 0.00;

        $deliveryTax = $deliveryFee * (($charges->delivery_charge_tax ?? 0.0) / 100);
        $packagingTax = $packagingCharge * (($charges->packaging_charge_tax ?? 0.0) / 100);
        $platformTax = $platformFee * (($charges->platform_fee_tax ?? 0.0) / 100);

        $subtotal = array_reduce($items, function ($carry, $item) {
            return $carry + ($item['price'] * $item['quantity']);
        }, 0);

        // Food Tax (fallback 5% for food, assume products don't have individual tax in this requirement)
        $foodTax = $subtotal * 0.05;

        $discount = 0;
        $couponId = null;

        // 3. Handle Coupon if provided
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

        $totalMerchantFees = $deliveryFee + $packagingCharge + $platformFee;
        $totalMerchantTaxes = $deliveryTax + $packagingTax + $platformTax;

        $totalPrice = ($subtotal - $discount) + $totalMerchantFees + $totalMerchantTaxes + $foodTax;
        if ($totalPrice < 0) $totalPrice = 0;

        // 4. Wrap order + payment creation in a transaction
        return DB::transaction(function () use ($data, $items, $totalPrice, $discount, $couponId, $MerchantId, $distance) {
            $orderData = [
                'user_id'        => $data['user_id'],
                'merchant_id'  => $MerchantId,
                'total_price'    => $totalPrice,
                'status'         => \App\Models\Order::STATUS_PLACED,
                'payment_status' => 'pending',
                'address'        => $data['delivery_address'],
                'user_lat'       => $data['latitude'] ?? null,
                'user_lng'       => $data['longitude'] ?? null,
                'distance_km'    => $distance ?? 0,
                'discount'       => $discount,
                'coupon_id'      => $couponId,
                'order_type'     => $data['order_type'] ?? \App\Models\Order::TYPE_DELIVERY,
                'estimated_delivery_time' => now()->addMinutes(30),
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

        if ($status === \App\Models\Order::STATUS_DELIVERED || $status === \App\Models\Order::STATUS_PICKED_UP) {
            $order->update(['actual_delivery_time' => now()]);
        }

        $order = $this->repository->updateStatus($id, $status);
        if ($order) {
            $order->load(['user']); // Ensure user info is loaded for notification
            // Notify user of status change
            if ($order->user && $order->user->fcm_token) {
                $statusMsg = match($status) {
                    \App\Models\Order::STATUS_ACCEPTED  => 'Your order has been accepted by the shop! ✅',
                    \App\Models\Order::STATUS_PREPARING => 'Your order is being prepared! 🧑‍🍳',
                    \App\Models\Order::STATUS_READY     => $order->order_type === \App\Models\Order::TYPE_PICKUP ? 'Your order is ready for pickup! 🎁' : 'Your order is ready to be picked up by a rider! 📦',
                    \App\Models\Order::STATUS_OUT_FOR_DELIVERY => 'Your order is on the way! 🛵💨',
                    \App\Models\Order::STATUS_DELIVERED  => 'Your order has been delivered! Enjoy! 🍽️',
                    \App\Models\Order::STATUS_PICKED_UP   => 'You have successfully picked up your order! Enjoy! 🛍️',
                    \App\Models\Order::STATUS_CANCELLED  => 'Your order has been cancelled.',
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

            return $order;
        }
        return null;
    }

    public function updatePaymentStatus($id, $paymentStatus)
    {
        $order = $this->repository->findById($id);
        if (!$order) return null;

        return DB::transaction(function () use ($order, $paymentStatus) {
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

    public function getOrders($MerchantId = null)
    {
        return $this->repository->getAll($MerchantId);
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

