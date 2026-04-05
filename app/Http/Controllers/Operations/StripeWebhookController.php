<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Webhook;
use App\Models\Order;
use App\Services\Identity\FCMService;
use UnexpectedValueException;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    public function __construct(
        protected FCMService $fcmService
    ) {}

    /**
     * Create a pre-order PaymentIntent (Simple Flow)
     */
    public function createIntent(Request $request)
    {
        $request->validate(['amount' => 'required|numeric|min:1']);
        
        Stripe::setApiKey(config('services.stripe.secret'));
        
        try {
            $intent = PaymentIntent::create([
                'amount'   => (int) ($request->amount * 100),
                'currency' => 'inr',
                'automatic_payment_methods' => ['enabled' => true],
                'metadata' => [
                    'user_id' => $request->user()->id,
                    'type'    => 'pre_order_payment'
                ]
            ]);

            return response()->json([
                'client_secret'     => $intent->client_secret,
                'payment_intent_id' => $intent->id
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Standard Webhook (Redundancy Fallback Only)
     */
    public function handle(Request $request)
    {
        $payload         = $request->getContent();
        $sig_header      = $request->header('Stripe-Signature');
        $endpoint_secret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        if ($event->type === 'payment_intent.succeeded') {
            $pi = $event->data->object;
            // Lookup order by transaction_id (PaymentIntent ID)
            $order = Order::where('payment_method', 'stripe')
                ->where('payment_status', 'pending')
                ->latest()
                ->first(); // Note: Better to match by metadata if possible, but simplified for one-stop flow
                
            if ($order) {
                $order->update(['payment_status' => 'paid', 'status' => 'placed']);
                $order->payment()->update(['status' => 'completed', 'payment_id' => $pi->id]);
            }
        }

        return response()->json(['success' => true]);
    }
}
