<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Models\UserAddress;
use Illuminate\Http\Request;

class UserAddressController extends Controller
{
    /**
     * Get the user's addresses.
     */
    public function index(Request $request)
    {
        $addresses = $request->user()->addresses()
            ->orderBy('is_default', 'desc')
            ->latest()
            ->get();
            
        return response()->json(['success' => true, 'data' => $addresses]);
    }

    /**
     * Store a newly created address in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'address_line' => 'required|string',
            'is_default' => 'boolean'
        ]);

        if ($request->is_default) {
            $request->user()->addresses()->update(['is_default' => false]);
            // Update the users table direct address string fallback
            $request->user()->update(['address' => $request->address_line]);
        }

        $address = $request->user()->addresses()->create([
            'address_line' => $request->address_line,
            'is_default' => $request->is_default ?? false,
        ]);

        // Auto-set default for the first address entry
        if ($request->user()->addresses()->count() === 1) {
            $address->update(['is_default' => true]);
            $request->user()->update(['address' => $request->address_line]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile address synchronization complete.',
            'data'    => $address
        ]);
    }
}
