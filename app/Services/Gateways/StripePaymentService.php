<?php

namespace App\Services\Gateways;

use Stripe\Stripe;
use Stripe\PaymentIntent;
use App\Models\Order;

class StripePaymentService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function createPaymentIntent(Order $order)
    {
        try {
            $intent = PaymentIntent::create([
                'amount'   => (int) ($order->total_price * 100), // Stripe uses paise (1 INR = 100 paise)
                'currency' => 'inr',
                'metadata' => [
                    'order_id' => $order->id,
                    'customer_email' => $order->user->email,
                ],
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

            return [
                'success' => true,
                'client_secret' => $intent->client_secret
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
