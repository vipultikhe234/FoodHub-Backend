<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserAddress;

class UserAddressController extends Controller
{
    public function index(Request $request)
    {
        $addresses = $request->user()->addresses()->orderBy('is_default', 'desc')->latest()->get();
        return response()->json(['success' => true, 'data' => $addresses]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'address_line' => 'required|string',
            'is_default' => 'boolean'
        ]);

        if ($request->is_default) {
            $request->user()->addresses()->update(['is_default' => false]);
            // Update the users table direct address string if they expect it 
            $request->user()->update(['address' => $request->address_line]);
        }

        $address = $request->user()->addresses()->create([
            'address_line' => $request->address_line,
            'is_default' => $request->is_default ?? false,
        ]);

        // If it's their first address, make it default and save to user obj
        if ($request->user()->addresses()->count() === 1) {
            $address->update(['is_default' => true]);
            $request->user()->update(['address' => $request->address_line]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Address added successfully',
            'data' => $address
        ]);
    }
}
