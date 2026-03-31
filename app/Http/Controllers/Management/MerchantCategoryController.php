<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\MerchantCategory;
use Illuminate\Http\Request;

class MerchantCategoryController extends Controller
{
    /**
     * List all merchant categories.
     */
    public function index()
    {
        $categories = MerchantCategory::latest()->get();
        return response()->json(['data' => $categories]);
    }

    /**
     * Store a new merchant category.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active'   => 'nullable|boolean',
        ]);

        $category = MerchantCategory::create($validated);

        return response()->json([
            'message' => 'Merchant category created successfully.',
            'data'    => $category
        ], 201);
    }

    /**
     * Update a merchant category.
     */
    public function update(Request $request, $id)
    {
        $category = MerchantCategory::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'is_active'   => 'nullable|boolean',
        ]);

        $category->update($validated);

        return response()->json([
            'message' => 'Merchant category updated successfully.',
            'data'    => $category
        ]);
    }

    /**
     * Delete a merchant category.
     */
    public function destroy($id)
    {
        $category = MerchantCategory::findOrFail($id);
        
        // Optional: Check if any merchant is using this category
        if ($category->merchants()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category that is currently linked to merchants.'
            ], 422);
        }

        $category->delete();

        return response()->json(['message' => 'Merchant category deleted successfully.']);
    }

    /**
     * Toggle active status.
     */
    public function toggleStatus($id)
    {
        $category = MerchantCategory::findOrFail($id);
        $category->is_active = !$category->is_active;
        $category->save();

        return response()->json([
            'message'   => 'Category status toggled successfully.',
            'is_active' => $category->is_active
        ]);
    }
}
