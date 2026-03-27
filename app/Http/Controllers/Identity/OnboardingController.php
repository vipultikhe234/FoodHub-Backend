<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class OnboardingController extends Controller
{
    /**
     * Admin registers a rider for a specific merchant.
     */
    public function adminOnboardRider(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'phone' => 'required|string|unique:users',
            'password' => 'required|string|min:8',
            'merchant_id' => 'nullable|exists:users,id'
        ]);

        $rider = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => User::ROLE_RIDER,
            'merchant_id' => $request->merchant_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rider onboarded by Admin.',
            'rider' => $rider
        ], 201);
    }

    /**
     * Merchant registers a rider for their own store.
     */
    public function merchantOnboardRider(Request $request)
    {
        if ($request->user()->role !== User::ROLE_MERCHANT) {
            return response()->json(['message' => 'Unauthorized. Must be a merchant.'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'phone' => 'required|string|unique:users',
            'password' => 'required|string|min:8'
        ]);

        $rider = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => User::ROLE_RIDER,
            'merchant_id' => $request->user()->id, // Linked to the merchant
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rider onboarded for your store.',
            'rider' => $rider
        ], 201);
    }
}
