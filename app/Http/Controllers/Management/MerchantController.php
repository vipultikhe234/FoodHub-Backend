<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MerchantController extends Controller
{
    /**
     * List all active Merchants (Public API).
     */
    public function index(Request $request)
    {
        $query = Merchant::with(['city.state.country', 'otherCharges'])
            ->where('is_active', true);

        if ($request->has('city_id')) {
            $query->where('city_id', $request->city_id);
        }

        return response()->json(['data' => $query->latest()->get()]);
    }

    /**
     * Get a public Merchant profile (Public API).
     */
    public function showPublic($id)
    {
        $Merchant = Merchant::with(['city.state.country', 'otherCharges'])
            ->where('is_active', true)
            ->findOrFail($id);

        return response()->json(['data' => $Merchant]);
    }

    /**
     * Get the merchant's Merchant profile.
     */
    public function show(Request $request)
    {
        $merchant = $request->user()->merchant()->with(['user', 'otherCharges'])->first();
        
        if (!$merchant) {
            return response()->json(['message' => 'No merchant node found for this identity.'], 404);
        }

        return response()->json(['data' => $merchant]);
    }

    /**
     * Update the Merchant profile.
     */
    public function update(Request $request)
    {
        $merchant = $request->user()->merchant;

        if (!$merchant) {
            return response()->json(['message' => 'No Merchant context identified.'], 404);
        }

        $validated = $request->validate([
            'name'                  => 'nullable|string|max:255',
            'description'           => 'nullable|string',
            'address'               => 'nullable|string',
            'country_id'            => 'nullable|numeric',
            'state_id'              => 'nullable|numeric',
            'city_id'               => 'nullable|numeric',
            'image'                 => 'nullable|string',
            'is_open'               => 'nullable',
            'opening_time'          => 'nullable|string',
            'closing_time'          => 'nullable|string',
            'delivery_charge'       => 'nullable|numeric',
            'packaging_charge'      => 'nullable|numeric',
            'platform_fee'          => 'nullable|numeric',
            'delivery_charge_tax'   => 'nullable|numeric',
            'packaging_charge_tax'  => 'nullable|numeric',
            'platform_fee_tax'      => 'nullable|numeric',
            'latitude'              => 'nullable|numeric',
            'longitude'             => 'nullable|numeric',
            'delivery_charge_type'  => 'nullable|in:fixed,distance',
            'delivery_charge_per_km'=> 'nullable|numeric',
            'max_delivery_distance' => 'nullable|numeric',
        ]);

        // Convert is_open to boolean
        if (isset($validated['is_open'])) {
            $validated['is_open'] = filter_var($validated['is_open'], FILTER_VALIDATE_BOOLEAN);
        }

        DB::transaction(function () use ($merchant, $validated, $request) {
            $merchant->update($validated);

            // Update or Create charges
            $chargeData = $request->only([
                'delivery_charge', 'packaging_charge', 'platform_fee',
                'delivery_charge_tax', 'packaging_charge_tax', 'platform_fee_tax',
                'delivery_charge_type', 'delivery_charge_per_km', 'max_delivery_distance'
            ]);

            if (!empty($chargeData)) {
                $merchant->otherCharges()->updateOrCreate(
                    ['merchant_id' => $merchant->id],
                    $chargeData
                );
            }
        });

        return response()->json([
            'message' => 'Merchant profile synchronized successfully.',
            'data'    => $merchant->load(['city.state.country', 'otherCharges'])
        ]);
    }

    /**
     * Store a newly created merchant and Merchant (Super Admin only).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'merchant_name'   => 'required|string|max:255',
            'email'           => 'required|email|unique:users,email',
            'password'        => 'required|string|min:6',
            'Merchant_name' => 'required|string|max:255',
            'address'         => 'required|string',
            'country_id'      => 'nullable|exists:countries,id',
            'state_id'        => 'nullable|exists:states,id',
            'city_id'         => 'nullable|exists:cities,id',
            'image'           => 'nullable|string',
            'opening_time'    => 'nullable|string',
            'closing_time'    => 'nullable|string',
            'delivery_charge'       => 'nullable|numeric',
            'packaging_charge'      => 'nullable|numeric',
            'platform_fee'          => 'nullable|numeric',
            'delivery_charge_tax'   => 'nullable|numeric',
            'packaging_charge_tax'  => 'nullable|numeric',
            'platform_fee_tax'      => 'nullable|numeric',
        ]);

        return DB::transaction(function () use ($validated) {
            // 1. Create the user
            $user = User::create([
                'name'     => $validated['merchant_name'],
                'email'    => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role'     => 'merchant',
            ]);

            // 2. Create the Merchant node
            $merchant = Merchant::create([
                'user_id'  => $user->id,
                'name'         => $validated['Merchant_name'],
                'address'      => $validated['address'],
                'country_id'   => $validated['country_id'] ?? null,
                'state_id'     => $validated['state_id'] ?? null,
                'city_id'      => $validated['city_id'] ?? null,
                'image'        => $validated['image'] ?? 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5',
                'opening_time' => $validated['opening_time'] ?? '09:00:00',
                'closing_time' => $validated['closing_time'] ?? '22:00:00',
                'is_open'      => true,
            ]);

            // 3. Create initial charges
            $merchant->otherCharges()->create([
                'delivery_charge'       => $validated['delivery_charge'] ?? 20.00,
                'packaging_charge'      => $validated['packaging_charge'] ?? 10.00,
                'platform_fee'          => $validated['platform_fee'] ?? 5.00,
                'delivery_charge_tax'   => $validated['delivery_charge_tax'] ?? 5.0,
                'packaging_charge_tax'  => $validated['packaging_charge_tax'] ?? 18.0,
                'platform_fee_tax'      => $validated['platform_fee_tax'] ?? 18.0,
            ]);

            return response()->json([
                'message'    => 'Merchant ecosystem provisioned successfully.',
                'merchant' => $merchant->load(['user', 'city.state.country', 'otherCharges'])
            ], 201);
        });
    }

    /**
     * Update an existing merchant and Merchant (Super Admin only).
     */
    public function adminUpdate(Request $request, $id)
    {
        $merchant = Merchant::findOrFail($id);
        
        $validated = $request->validate([
            'merchant_name'   => 'sometimes|string|max:255',
            'email'           => 'sometimes|email|unique:users,email,' . $merchant->user_id,
            'password'        => 'sometimes|nullable|string|min:6',
            'Merchant_name' => 'sometimes|string|max:255',
            'address'         => 'sometimes|string',
            'country_id'      => 'nullable|exists:countries,id',
            'state_id'        => 'nullable|exists:states,id',
            'city_id'         => 'nullable|exists:cities,id',
            'image'           => 'nullable|string',
            'opening_time'    => 'nullable|string',
            'closing_time'    => 'nullable|string',
            'delivery_charge'       => 'nullable|numeric',
            'packaging_charge'      => 'nullable|numeric',
            'platform_fee'          => 'nullable|numeric',
            'delivery_charge_tax'   => 'nullable|numeric',
            'packaging_charge_tax'  => 'nullable|numeric',
            'platform_fee_tax'      => 'nullable|numeric',
            'latitude'              => 'nullable|numeric',
            'longitude'             => 'nullable|numeric',
            'delivery_charge_type'  => 'nullable|in:fixed,distance',
            'delivery_charge_per_km'=> 'nullable|numeric',
            'max_delivery_distance' => 'nullable|numeric',
        ]);

        return DB::transaction(function () use ($validated, $merchant, $request) {
            // Update User
            $userData = [];
            if (isset($validated['merchant_name'])) $userData['name'] = $validated['merchant_name'];
            if (isset($validated['email'])) $userData['email'] = $validated['email'];
            if (!empty($validated['password'])) $userData['password'] = Hash::make($validated['password']);
            
            if (!empty($userData)) {
                $merchant->user->update($userData);
            }

            // Update Merchant Node
            $restData = [];
            if (isset($validated['Merchant_name'])) $restData['name'] = $validated['Merchant_name'];
            if (isset($validated['address'])) $restData['address'] = $validated['address'];
            if (array_key_exists('country_id', $validated)) $restData['country_id'] = $validated['country_id'];
            if (array_key_exists('state_id', $validated)) $restData['state_id'] = $validated['state_id'];
            if (array_key_exists('city_id', $validated)) $restData['city_id'] = $validated['city_id'];
            if (isset($validated['image'])) $restData['image'] = $validated['image'];
            if (isset($validated['opening_time'])) $restData['opening_time'] = $validated['opening_time'];
            if (isset($validated['closing_time'])) $restData['closing_time'] = $validated['closing_time'];
            if (isset($validated['latitude'])) $restData['latitude'] = $validated['latitude'];
            if (isset($validated['longitude'])) $restData['longitude'] = $validated['longitude'];
            
            $merchant->update($restData);

            // Update Charges
            $chargeData = $request->only([
                'delivery_charge', 'packaging_charge', 'platform_fee',
                'delivery_charge_tax', 'packaging_charge_tax', 'platform_fee_tax',
                'delivery_charge_type', 'delivery_charge_per_km', 'max_delivery_distance'
            ]);

            if (!empty($chargeData)) {
                $merchant->otherCharges()->updateOrCreate(
                    ['merchant_id' => $merchant->id],
                    $chargeData
                );
            }

            return response()->json([
                'message'    => 'Merchant ecosystem updated successfully.',
                'merchant' => $merchant->load(['user', 'city.state.country', 'otherCharges'])
            ]);
        });
    }

    /**
     * List all Merchants (Super Admin only).
     */
    public function listAll()
    {
        return response()->json(['data' => Merchant::with(['user', 'city.state.country', 'otherCharges'])->latest()->get()]);
    }

    /**
     * Toggle Merchant active status (Super Admin).
     */
    public function toggleStatus($id)
    {
        $merchant = Merchant::findOrFail($id);
        $merchant->is_active = !$merchant->is_active;
        $merchant->save();

        return response()->json([
            'message'   => 'Node status toggled successfully.',
            'is_active' => $merchant->is_active
        ]);
    }

    /**
     * Get paginated reviews for a merchant (Public).
     */
    public function reviews($id)
    {
        $reviews = \App\Models\Review::with('user')
            ->where('merchant_id', $id)
            ->latest()
            ->paginate(15);

        // Calculate average rating
        $avgRating = \App\Models\Review::where('merchant_id', $id)->avg('rating');
        $totalReviews = \App\Models\Review::where('merchant_id', $id)->count();

        return response()->json([
            'data'         => \App\Http\Resources\ReviewResource::collection($reviews),
            'avg_rating'   => round((float) $avgRating, 1),
            'total_reviews'=> $totalReviews,
            'meta'         => [
                'current_page' => $reviews->currentPage(),
                'last_page'    => $reviews->lastPage(),
                'per_page'     => $reviews->perPage(),
                'total'        => $reviews->total(),
            ]
        ]);
    }

    /**
     * Submit a review for a merchant (Authenticated user with delivered order).
     */
    public function addReview(Request $request, $id)
    {
        $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'rating'   => 'required|integer|min:1|max:5',
            'review'   => 'nullable|string|max:1000',
        ]);

        // Verify order: belongs to user, belongs to merchant, and is delivered
        $order = \App\Models\Order::where('id', $request->order_id)
            ->where('user_id', $request->user()->id)
            ->where('merchant_id', $id)
            ->where('status', 'delivered')
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'You can only review a merchant after a delivered order.'
            ], 403);
        }

        // Prevent duplicate review for same order
        $existing = \App\Models\Review::where('user_id', $request->user()->id)
            ->where('order_id', $order->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'You have already reviewed this order.'], 409);
        }

        $review = \App\Models\Review::create([
            'user_id'     => $request->user()->id,
            'merchant_id' => $id,
            'order_id'    => $order->id,
            'rating'      => $request->rating,
            'review'      => $request->review,
        ]);

        return response()->json([
            'message' => 'Review submitted successfully.',
            'data'    => new \App\Http\Resources\ReviewResource($review->load('user')),
        ], 201);
    }
}

