<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Merchant;
use App\Http\Resources\ReviewResource;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * List reviews (Admin/Merchant Management).
     */
    public function index(Request $request)
    {
        $query = Review::with(['user', 'merchant', 'order', 'product']);

        // Merchant-specific scoping
        if ($request->user()->role === 'merchant') {
            $merchant = $request->user()->merchant;
            if (!$merchant) {
                return response()->json(['message' => 'No merchant node found'], 404);
            }
            $query->where('merchant_id', $merchant->id);
        } elseif ($request->filled('merchant_id')) {
            $query->where('merchant_id', $request->merchant_id);
        }

        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        $reviews = $query->latest()->paginate(20);

        return ReviewResource::collection($reviews);
    }

    /**
     * Get review stats for a merchant.
     */
    public function stats(Request $request)
    {
        $merchantId = $request->query('merchant_id');

        if ($request->user()->role === 'merchant') {
            $merchantId = $request->user()->merchant?->id;
        }

        if (!$merchantId) {
            return response()->json(['message' => 'Merchant ID required'], 400);
        }

        $stats = Review::where('merchant_id', $merchantId)
            ->selectRaw('rating, count(*) as count')
            ->groupBy('rating')
            ->get();

        $avgRating = Review::where('merchant_id', $merchantId)->avg('rating');
        $total = Review::where('merchant_id', $merchantId)->count();

        return response()->json([
            'avg_rating' => round($avgRating, 1),
            'total_reviews' => $total,
            'breakdown' => $stats
        ]);
    }

    /**
     * Delete a review (Admin only).
     */
    public function destroy($id)
    {
        $review = Review::findOrFail($id);
        $review->delete();

        return response()->json(['message' => 'Review deleted successfully']);
    }
}
