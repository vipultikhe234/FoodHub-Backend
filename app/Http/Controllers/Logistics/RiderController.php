<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Services\Logistics\RiderService;
use App\Models\User;
use Illuminate\Http\Request;
use Exception;

class RiderController extends Controller
{
    public function __construct(protected RiderService $riderService) {}

    public function availableOrders()
    {
        try {
            return response()->json([
                'success' => true,
                'orders'  => $this->riderService->getAvailableOrders()
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function acceptOrder(Request $request, $id)
    {
        try {
            $order = $this->riderService->acceptOrder($request->user()->id, $id);
            return response()->json(['success' => true, 'order' => $order]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function updateLocation(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric'
        ]);

        try {
            $this->riderService->updateRiderLocation($request->user()->id, $request->lat, $request->lng);
            return response()->json(['success' => true, 'message' => 'Location updated.']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function completeOrder(Request $request, $id)
    {
        try {
            $order = $this->riderService->completeDelivery($request->user()->id, $id);
            return response()->json(['success' => true, 'order' => $order]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function history(Request $request)
    {
        try {
            return response()->json([
                'success' => true,
                'orders'  => $this->riderService->getRiderHistory($request->user()->id)
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function listStaff(Request $request)
    {
        try {
            $user = $request->user();
            $query = User::where('role', User::ROLE_RIDER);

            // 1. If merchant, only show their riders
            if ($user->role === User::ROLE_MERCHANT) {
                $query->where('merchant_id', $user->id);
            } 
            // 2. If Admin with merchant_id filter
            elseif ($user->role === User::ROLE_ADMIN && $request->has('merchant_id')) {
                $query->where('merchant_id', $request->merchant_id);
            }

            return response()->json([
                'success' => true,
                'data' => $query->latest()->get()
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
