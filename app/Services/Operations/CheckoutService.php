<?php

namespace App\Services\Operations;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Inventory;
use App\Models\Merchant;
use App\Models\UserAddress;
use App\Models\Coupon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CheckoutService
{
    public function __construct(
        protected \App\Services\Identity\FCMService $fcmService
    ) {
    }

    /**
     * Complete the checkout process in a single atomic transaction.
     * Follows the 'ACID Path' with stock reservation and data snapshotting.
     */
    public function process(array $data, $user)
    {
        // 0. Stripe Verification Guard (Simple Flow: Verify BEFORE Insert)
        if (($data['payment_method'] ?? 'COD') === 'stripe') {
            if (empty($data['payment_intent_id'])) {
                throw new \Exception("Stripe Payment Intent ID is required for card orders.");
            }

            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            try {
                $intent = \Stripe\PaymentIntent::retrieve($data['payment_intent_id']);
                if ($intent->status !== 'succeeded') {
                    throw new \Exception("Stripe Payment not confirmed. Current status: " . $intent->status);
                }
            } catch (\Exception $e) {
                throw new \Exception("Stripe Verification Failed: " . $e->getMessage());
            }
        }

        return DB::transaction(function () use ($data, $user) {
            // 1. Resolve Global Entities
            $Merchant = Merchant::with('user')->findOrFail($data['merchant_id']);
            $address = UserAddress::find($data['address_id'] ?? null);

            // 2. Initial Calculations
            $subtotal = 0;

            // 3. Process Items & Resolve Stock
            foreach ($data['items'] as $item) {
                $itemSubtotal = $item['unit_price'] * $item['quantity'];
                $subtotal += $itemSubtotal;

                // Resolve Stock Context
                $inventory = null;
                if (!empty($item['product_variant_id'])) {
                    $inventory = Inventory::where('product_variant_id', $item['product_variant_id'])
                        ->where('merchant_id', $Merchant->id)
                        ->lockForUpdate()
                        ->first();
                }

                // SECURITY GUARD: If product requires variants but none was given, it hits 'else' branch naturally.
                if ($inventory) {
                    if (($inventory->stock - $inventory->reserved_stock) < $item['quantity']) {
                        throw new \Exception("Item '{$item['product_name']}' ({$item['variant_name']}) is out of stock.");
                    }
                    $inventory->increment('reserved_stock', $item['quantity']);
                } else {
                    // Fail-early for variants that are missing inventory records
                    if (!empty($item['product_variant_id'])) {
                        throw new \Exception("Selection '{$item['variant_name']}' for '{$item['product_name']}' is currently unavailable.");
                    }

                    $product = \App\Models\Product::find($item['product_id']);
                    // If product itself has variants but we reached here without a variant, reject
                    if ($product && $product->has_variants) {
                        throw new \Exception("Item '{$item['product_name']}' require a specific selection.");
                    }

                    if (!$product || $product->stock < $item['quantity']) {
                        throw new \Exception("Item '{$item['product_name']}' is out of stock.");
                    }
                    $product->decrement('stock', $item['quantity']);
                }
            }

            // Trust Mobile App Calculations as explicitly requested to avoid discrepancies
            $deliveryFee = floatval($data['delivery_fee'] ?? 0);
            $deliveryTax = floatval($data['delivery_tax'] ?? 0);
            $packagingFee = floatval($data['packing_charge'] ?? 0);
            $packagingTax = floatval($data['packaging_tax'] ?? 0);
            $platformFee = floatval($data['platform_fee'] ?? 0);
            $platformTax = floatval($data['platform_tax'] ?? 0);
            $itemsTax = floatval($data['items_tax'] ?? 0);
            $taxAmount = floatval($data['tax_amount'] ?? 0);
            $couponDiscount = floatval($data['coupon_discount'] ?? 0);
            $totalAmount = floatval($data['total_price'] ?? 0);

            // Coupon ID Resolution (Priority to ID, fallback to code)
            $couponId = $data['coupon_id'] ?? null;
            if (!$couponId && !empty($data['coupon_code'])) {
                $couponId = \App\Models\Coupon::where('code', $data['coupon_code'])->value('id');
            }

            // Generate Order Number
            $prefix = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 3);
            $orderNumber = $prefix . rand(1000000, 9999999);

            // 5. Create Order Header
            $isStripe = ($data['payment_method'] ?? 'COD') === 'stripe';

            $order = Order::create([
                'order_number' => $orderNumber,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'user_id' => $user->id,
                'merchant_id' => $Merchant->id,
                'subtotal' => round($subtotal, 2),
                'items_tax' => $itemsTax,
                'tax_amount' => $taxAmount,
                'delivery_fee' => $deliveryFee,
                'delivery_tax' => $deliveryTax,
                'packaging_fee' => $packagingFee,
                'packaging_tax' => $packagingTax,
                'platform_fee' => $platformFee,
                'platform_tax' => $platformTax,
                'coupon_id' => $couponId,
                'coupon_discount' => $couponDiscount,
                'total_price' => $totalAmount,
                'address_id' => $address?->id,
                'payment_method' => $data['payment_method'] ?? 'COD',
                'payment_status' => $isStripe ? 'paid' : 'pending',
                'status' => 'placed',
                'order_type' => $data['order_type'] ?? 'delivery',
                'notes' => $data['notes'] ?? null,
            ]);

            // 6. Create Items
            foreach ($data['items'] as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'price' => $item['unit_price'],
                    'total' => $item['unit_price'] * $item['quantity'],
                ]);
            }

            // 7. Create Payment Record (ONLY for Stripe, per user request: Cash = No Payment Record yet)
            if ($isStripe) {
                $order->payment()->create([
                    'payment_method' => 'stripe',
                    'amount' => $totalAmount,
                    'status' => 'completed',
                    'payment_id' => $data['payment_intent_id'] ?? null,
                ]);
            }

            // 8. Notification Logic (Unified & Safe!)
            try {
                // Notify Merchant
                if ($Merchant->user && $Merchant->user->fcm_token) {
                    $this->fcmService->sendNotification(
                        $Merchant->user->fcm_token,
                        '🚀 New Order!',
                        "New order #{$order->order_number} received from {$user->name}",
                        ['type' => 'new_order', 'order_id' => (string) $order->id],
                        $Merchant->user->id
                    );
                }

                // Notify User
                if ($user->fcm_token) {
                    $this->fcmService->sendNotification(
                        $user->fcm_token,
                        '📦 Order Placed!',
                        "Your order #{$order->order_number} has been placed successfully.",
                        ['type' => 'order_status', 'order_id' => (string) $order->id, 'status' => 'placed'],
                        $user->id
                    );
                }
            } catch (\Exception $e) {
                // Log and ignore to prevent checkout crash
                \Log::warning("Notification failed for order #{$order->id}: " . $e->getMessage());
            }

            return $order;
        });
    }
}

