<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    /**
     * Called by the frontend AFTER Stripe.js confirms payment.
     * Verifies the PaymentIntent with Stripe directly (source of truth)
     * and marks the order as paid.
     * This is the local dev alternative to webhooks (which need a public URL).
     */
    public function confirmPayment(Request $request)
    {
        $request->validate([
            'order_id'          => 'required|integer',
            'payment_intent_id' => 'required|string',
        ]);

        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            $intent = PaymentIntent::retrieve($request->payment_intent_id);

            if ($intent->status !== 'succeeded') {
                return response()->json([
                    'message' => 'Payment not confirmed by Stripe. Status: ' . $intent->status
                ], 422);
            }

            $order = Order::with('payment')->find($request->order_id);

            if (!$order) {
                return response()->json(['message' => 'Order not found'], 404);
            }

            if ($order->user_id !== $request->user()->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $order->update([
                'payment_status' => 'paid',
                'status'         => 'preparing',
            ]);

            $order->payment()->update([
                'status'         => 'completed',
                'transaction_id' => $intent->id,
            ]);

            return response()->json([
                'message' => 'Payment confirmed',
                'data'    => $order->load('payment'),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Verification failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Stripe Webhook handler (production — requires a public URL).
     */
    public function handle(Request $request)
    {
        $payload         = $request->getContent();
        $sig_header      = $request->header('Stripe-Signature');
        $endpoint_secret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
        } catch (UnexpectedValueException $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        switch ($event->type) {
            case 'payment_intent.succeeded':
                $pi    = $event->data->object;
                $order = Order::find($pi->metadata->order_id);
                if ($order) {
                    $order->update(['payment_status' => 'paid', 'status' => 'preparing']);
                    $order->payment()->update(['status' => 'completed', 'transaction_id' => $pi->id]);
                }
                break;

            case 'payment_intent.payment_failed':
                $pi    = $event->data->object;
                $order = Order::find($pi->metadata->order_id);
                if ($order) {
                    $order->update(['payment_status' => 'failed']);
                    $order->payment()->update(['status' => 'failed']);
                }
                break;
        }

        return response()->json(['success' => true]);
    }
}
