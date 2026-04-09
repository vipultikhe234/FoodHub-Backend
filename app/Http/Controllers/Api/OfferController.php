<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\LandingOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class OfferController extends Controller
{
    /**
     * Display a listing of active offers across all merchants.
     */
    public function index(Request $request)
    {
        // Caching for 5 minutes since offers don't change by the second
        return Cache::remember('active_live_offers', 300, function () use ($request) {
            return Offer::with(['merchant:id,name,image', 'product:id,name,image', 'category:id,name,image'])
                ->active()
                ->orderBy('priority', 'desc')
                ->orderBy('discount_value', 'desc')
                ->orderBy('usage_count', 'desc')
                ->limit(20)
                ->get()
                ->map(function ($offer) {
                    return [
                        'id' => $offer->id,
                        'title' => $offer->title,
                        'description' => $offer->description,
                        'banner' => $offer->banner_url,
                        'discount' => [
                            'type' => $offer->discount_type,
                            'value' => $offer->discount_value,
                            'label' => $offer->discount_type === 'percentage' 
                                ? "{$offer->discount_value}% OFF" 
                                : "₹{$offer->discount_value} OFF"
                        ],
                        'merchant' => [
                            'id' => $offer->merchant_id,
                            'name' => $offer->merchant->name ?? 'ApnaCart Merchant',
                        ],
                        'target' => [
                            'type' => $offer->product_id ? 'product' : ($offer->category_id ? 'category' : 'store'),
                            'id' => $offer->product_id ?? $offer->category_id ?? $offer->merchant_id,
                        ],
                        'validity' => $offer->end_date ? 'Valid till ' . $offer->end_date->format('jS M') : 'Limited Time',
                        'is_hot' => $offer->priority >= 10,
                    ];
                });
        });
    }

    /**
     * List all offers for management (Admin/Merchant)
     */
    public function listAll(Request $request)
    {
        $query = Offer::with(['merchant', 'product', 'category']);
        
        if ($request->merchant_id) {
            $query->where('merchant_id', $request->merchant_id);
        }

        if ($request->has('status') && $request->status !== 'all') {
            $isActive = $request->status === 'active';
            if ($isActive) {
                $query->where('is_active', true)
                      ->where(function($q) {
                          $q->whereNull('end_date')->orWhere('end_date', '>=', now()->startOfDay());
                      });
            } else {
                $query->where(function($q) {
                    $q->where('is_active', false)->orWhere('end_date', '<', now()->startOfDay());
                });
            }
        }

        return response()->json([
            'data' => $query->orderBy('priority', 'desc')->orderBy('id', 'desc')->get()
        ]);
    }

    /**
     * Store a newly created offer.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'merchant_id' => 'required|exists:merchants,id',
            'category_id' => 'nullable|exists:categories,id',
            'product_id' => 'nullable|exists:products,id',
            'title' => 'required|string',
            'description' => 'nullable|string',
            'banner_url' => 'nullable|string',
            'discount_type' => 'required|in:percentage,flat',
            'discount_value' => 'required|numeric',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'priority' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'show_on_landing' => 'nullable|boolean',
        ]);

        $offer = Offer::create($data);
        Cache::forget('active_live_offers');

        if ($offer->show_on_landing) {
            LandingOffer::updateOrCreate(
                ['type' => 'offer', 'source_id' => $offer->id],
                [
                    'title' => $offer->title,
                    'subtitle' => $offer->description,
                    'image' => $offer->banner_url,
                    'link' => "/Merchant/{$offer->merchant_id}",
                    'merchant_id' => $offer->merchant_id
                ]
            );
        }

        return response()->json(['success' => true, 'data' => $offer]);
    }

    /**
     * Update an existing offer.
     */
    public function update(Request $request, $id)
    {
        $offer = Offer::findOrFail($id);
        
        $data = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'product_id' => 'nullable|exists:products,id',
            'title' => 'string',
            'description' => 'nullable|string',
            'banner_url' => 'nullable|string',
            'discount_type' => 'in:percentage,flat',
            'discount_value' => 'numeric',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'priority' => 'integer',
            'is_active' => 'boolean',
            'show_on_landing' => 'nullable|boolean',
        ]);

        $offer->update($data);
        Cache::forget('active_live_offers');

        if ($offer->show_on_landing) {
            LandingOffer::updateOrCreate(
                ['type' => 'offer', 'source_id' => $offer->id],
                [
                    'title' => $offer->title,
                    'subtitle' => $offer->description,
                    'image' => $offer->banner_url,
                    'link' => "/Merchant/{$offer->merchant_id}",
                    'merchant_id' => $offer->merchant_id
                ]
            );
        } else {
            LandingOffer::where('type', 'offer')->where('source_id', $offer->id)->delete();
        }

        return response()->json(['success' => true, 'data' => $offer]);
    }

    /**
     * Remove an offer.
     */
    public function destroy($id)
    {
        $offer = Offer::findOrFail($id);
        $offer->delete();
        Cache::forget('active_live_offers');

        return response()->json(['success' => true]);
    }

    /**
     * Log offer click (Optional: for popularity sorting)
     */
    public function recordClick($id)
    {
        Offer::where('id', $id)->increment('usage_count');
        return response()->json(['success' => true]);
    }
}

