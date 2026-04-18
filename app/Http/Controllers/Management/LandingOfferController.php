<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\LandingOffer;
use Illuminate\Http\Request;

class LandingOfferController extends Controller
{
    /**
     * Display a listing of landing offers.
     */
    public function index(Request $request)
    {
        $now = now();
        $cityId = $request->query('city_id');
        $showAll = $request->query('all');

        if ($showAll) {
            // Fetch ALL active offers
            $offers = \App\Models\Offer::with(['merchant.merchantCategory'])
                ->active()
                ->where(function ($q) use ($now) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', $now->startOfDay());
                })
                ->when($cityId, function ($q) use ($cityId) {
                    $q->whereHas('merchant', function ($mq) use ($cityId) {
                        $mq->where('city_id', $cityId);
                    });
                })
                ->get()
                ->map(function ($offer) {
                    return [
                        'id' => 'offer-' . $offer->id,
                        'type' => 'offer',
                        'source_id' => $offer->id,
                        'title' => $offer->title,
                        'subtitle' => $offer->description,
                        'image' => $offer->banner_url,
                        'link' => "/Merchant/{$offer->merchant_id}",
                        'merchant_id' => $offer->merchant_id,
                        'merchant' => $offer->merchant ?: ['name' => 'ApnaCart Official']
                    ];
                });

            // Fetch ALL active coupons
            $coupons = \App\Models\Coupon::with(['merchant.merchantCategory'])
                ->active()
                ->when($cityId, function ($q) use ($cityId) {
                    $q->where(function ($qq) use ($cityId) {
                        $qq->whereHas('merchant', function ($mq) use ($cityId) {
                            $mq->where('city_id', $cityId);
                        })->orWhere('is_admin_coupon', true);
                    });
                })
                ->get()
                ->map(function ($coupon) {
                    $discountLabel = $coupon->type === 'percentage'
                        ? "{$coupon->value}% OFF"
                        : "₹" . number_format($coupon->value, 0) . " OFF";

                    return [
                        'id' => 'coupon-' . $coupon->id,
                        'type' => 'coupon',
                        'source_id' => $coupon->id,
                        'title' => $coupon->code,
                        'subtitle' => "USE {$coupon->code} GET {$discountLabel}",
                        'image' => null, // Coupons generally don't have banners unless assigned to landing_offers
                        'link' => $coupon->is_admin_coupon ? "/" : "/Merchant/{$coupon->merchant_id}",
                        'merchant_id' => $coupon->merchant_id,
                        'merchant' => $coupon->merchant ?: ['name' => 'ApnaCart Official']
                    ];
                });

            return response()->json($offers->concat($coupons)->values());
        }

        // Default: Fetch only landing offers
        $landingOffers = LandingOffer::with(['merchant.merchantCategory'])->get();

        // Filter based on city (if provided) and their source's current status
        $activeLandingOffers = $landingOffers->filter(function ($item) use ($now, $cityId) {
            // City check first
            // City check first: Bypass if it's an admin coupon
            if ($cityId && $item->merchant_id && $item->merchant?->city_id != $cityId) {
                // If it's a coupon, check if it's an admin coupon
                if ($item->type === 'coupon') {
                    $coupon = \App\Models\Coupon::find($item->source_id);
                    if (!$coupon || !$coupon->is_admin_coupon) {
                        return false;
                    }
                } else {
                    return false;
                }
            }

            if ($item->type === 'offer') {
                $source = \App\Models\Offer::find($item->source_id);
                if (!$source) return false;
                $isDateValid = !$source->end_date || $source->end_date >= $now->startOfDay();
                return $source->is_active && $isDateValid;
            } else {
                $source = \App\Models\Coupon::find($item->source_id);
                if (!$source) return false;
                $isDateValid = !$source->expires_at || $source->expires_at >= $now->startOfDay();
                return $source->is_active && $isDateValid;
            }
            if ($item->type === 'coupon') {
                $coupon = \App\Models\Coupon::find($item->source_id);
                if ($coupon && $coupon->is_admin_coupon) {
                    $item->link = "/";
                }
            }
            return true;
        })->values();

        return response()->json($activeLandingOffers);
    }
}
