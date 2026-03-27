<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Services\Identity\FCMService;

class CouponController extends Controller
{
    protected $fcmService;

    public function __construct(FCMService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    public function validateCoupon(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'order_amount' => 'required|numeric',
            'merchant_id' => 'nullable|exists:Merchants,id'
        ]);

        $query = Coupon::where('code', $request->code);

        if ($request->merchant_id) {
            $query->where(function($q) use ($request) {
                $q->whereNull('merchant_id')
                  ->orWhere('merchant_id', $request->merchant_id);
            });
        }

        $coupon = $query->active()->first();

        if (!$coupon) {
            return response()->json(['message' => 'Invalid or expired coupon code'], 404);
        }

        if ($request->order_amount < $coupon->min_order_amount) {
            return response()->json([
                'message' => 'Minimum order amount for this coupon is ₹' . $coupon->min_order_amount
            ], 400);
        }

        $discount = 0;
        if ($coupon->type === 'percentage') {
            $discount = ($request->order_amount * $coupon->value) / 100;
        } else {
            $discount = $coupon->value;
        }

        return response()->json([
            'message' => 'Coupon applied!',
            'discount' => round($discount, 2),
            'code' => $coupon->code
        ]);
    }

    public function index(Request $request)
    {
        $MerchantId = $request->query('merchant_id');
        $coupons = Coupon::byMerchant($MerchantId)->latest()->get();

        return response()->json([
            'data' => $coupons
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|unique:coupons,code',
            'type' => 'required|in:fixed,percentage',
            'value' => 'required|numeric',
            'min_order_amount' => 'required|numeric',
            'expires_at' => 'required|date',
            'is_active' => 'boolean'
        ]);

        if ($request->user()->role === 'merchant') {
            $validated['merchant_id'] = $request->user()->merchant?->id;
            if (!$validated['merchant_id']) return response()->json(['message' => 'No Merchant context'], 400);
        }

        $coupon = Coupon::create($validated);

        // Broadcast refresh
        $this->fcmService->broadcastData(['type' => 'refresh_coupons', 'action' => 'created', 'code' => $coupon->code]);

        return response()->json(['message' => 'Coupon created successfully', 'data' => $coupon], 201);
    }

    public function update(Request $request, $id)
    {
        $coupon = Coupon::findOrFail($id);

        if ($request->user()->role === 'merchant' && $coupon->merchant_id !== $request->user()->merchant?->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'code' => 'sometimes|unique:coupons,code,' . $id,
            'type' => 'sometimes|in:fixed,percentage',
            'value' => 'sometimes|numeric',
            'min_order_amount' => 'sometimes|numeric',
            'expires_at' => 'sometimes|date',
            'is_active' => 'boolean'
        ]);

        $coupon->update($validated);

        // Broadcast refresh
        $this->fcmService->broadcastData(['type' => 'refresh_coupons', 'action' => 'updated', 'id' => (string)$id]);

        return response()->json(['message' => 'Coupon updated successfully', 'data' => $coupon]);
    }

    public function destroy(Request $request, $id)
    {
        $coupon = Coupon::findOrFail($id);

        if ($request->user()->role === 'merchant' && $coupon->merchant_id !== $request->user()->merchant?->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $coupon->delete();

        // Broadcast refresh
        $this->fcmService->broadcastData(['type' => 'refresh_coupons', 'action' => 'deleted', 'id' => (string)$id]);

        return response()->json(['message' => 'Coupon deleted successfully']);
    }
}

