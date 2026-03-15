<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $service
    ) {}

    /**
     * Store a newly created order in storage.
     */
    public function store(\App\Http\Requests\StoreOrderRequest $request)
    {

        try {
            $data = $request->all();
            $data['user_id'] = $request->user()->id;

            $result = $this->service->placeOrder($data, $request->items);

            return response()->json([
                'message' => 'Order placed successfully',
                'data'    => $result
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to place order: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Display a single order (Owner or Admin).
     */
    public function show(Request $request, $id)
    {
        $order = \App\Models\Order::with(['items.product', 'user', 'payment'])->find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        // Only the owner or an admin can view the order
        if ($order->user_id !== $request->user()->id && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(['data' => $order]);
    }

    /**
     * Display a listing of orders (Admin or User specific).
     */
    public function index(Request $request)
    {
        if ($request->user()->role === 'admin') {
            $orders = $this->service->getOrders();
        } else {
            $orders = $this->service->getUserOrders($request->user()->id);
        }

        return response()->json(['data' => $orders]);
    }

    /**
     * Update the order status (Admin only).
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,preparing,dispatched,delivered,cancelled'
        ]);

        try {
            $order = $this->service->updateOrderStatus($id, $request->status);

            if (!$order) {
                return response()->json(['message' => 'Order not found'], 404);
            }

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
