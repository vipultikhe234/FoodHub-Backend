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
    public function index()
    {
        $now = now();
        
        // Fetch all landing offers with merchant and their category relation
        $landingOffers = LandingOffer::with(['merchant.merchantCategory'])->get();
        
        // Filter based on their source's current status
        $activeLandingOffers = $landingOffers->filter(function($item) use ($now) {
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
        })->values();

        return response()->json($activeLandingOffers);
    }
}
