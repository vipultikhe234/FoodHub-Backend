<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Services\Operations\OrderService;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Http\Requests\StoreOrderRequest;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $service,
        protected \App\Services\Operations\CheckoutService $checkoutService
    ) {}

    /**
     * Store a newly created order in storage.
     */
    public function store(StoreOrderRequest $request)
    {
        try {
            $data = $request->all();
            
            // ACP Path: Atomic Checkout Process
            $order = $this->checkoutService->process($data, $request->user());

            // If Stripe selected, resolve payment intent
            if ($data['payment_method'] === 'stripe') {
                $intent = $this->service->initiatePayment($order->id);
                return response()->json([
                    'message' => 'Order placed. Finalizing payment...',
                    'data'    => [
                        'order' => $order->load(['items', 'payment']),
                        'stripe_client_secret' => $intent['client_secret']
                    ]
                ], 201);
            }

            return response()->json([
                'message' => 'Order placed successfully',
                'data'    => [
                    'order' => $order->load(['items', 'payment'])
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Verification failed: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Display a single order (Owner, Admin, or Merchant).
     */
    public function show(Request $request, $id)
    {
        $order = Order::with(['items.product', 'user', 'payment', 'merchant'])->find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        // Check permissions: Admin, the Customer who placed it, or the Merchant who owns the Merchant
        $isOwner = $order->user_id === $request->user()->id;
        $isAdmin = $request->user()->role === 'admin';
        $isMerchant = $request->user()->role === 'merchant' && $order->merchant_id === $request->user()->merchant?->id;

        if (!$isOwner && !$isAdmin && !$isMerchant) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(['data' => $order]);
    }

    /**
     * Display a listing of orders (Admin, Merchant, or User specific).
     */
    public function index(Request $request)
    {
        if ($request->user()->role === 'admin' || $request->user()->role === 'merchant') {
            // Admins can optionally provide a merchant_id in query
            // Merchants are automatically scoped by the trait
            $merchantId = $request->query('merchant_id');
            $orders = $this->service->getOrders($merchantId);
        } else {
            $orders = $this->service->getUserOrders($request->user()->id);
        }

        return response()->json(['data' => $orders]);
    }

    /**
     * Update the order status (Admin or Merchant).
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:placed,accepted,preparing,ready,out_for_delivery,delivered,picked_up,cancelled'
        ]);

        $order = Order::find($id);
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        // Only Admin or the Merchant owning the Merchant can update status
        $isAdmin = $request->user()->role === 'admin';
        $isMerchant = $request->user()->role === 'merchant' && $order->merchant_id === $request->user()->merchant?->id;

        if (!$isAdmin && !$isMerchant) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $order = $this->service->updateOrderStatus($id, $request->status);
            return response()->json([
                'message' => 'Order status updated to ' . $request->status,
                'data' => $order
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update the payment status (Admin only).
     */
    public function updatePaymentStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,paid,failed,refunded'
        ]);

        try {
            $order = $this->service->updatePaymentStatus($id, $request->status);

            if (!$order) {
                return response()->json(['message' => 'Order not found'], 404);
            }

            return response()->json([
                'message' => 'Payment status updated to ' . $request->status,
                'data' => $order
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Payment update failed: ' . $e->getMessage()
            ], 400);
        }
    }

    public function initiatePayment($id)
    {
        try {
            $result = $this->service->initiatePayment($id);
            return response()->json(['data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}

