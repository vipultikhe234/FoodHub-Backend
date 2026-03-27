<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use App\Services\Identity\FCMService;

class FCMController extends Controller
{
    protected $fcmService;

    public function __construct(FCMService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Save/Update FCM token for the authenticated user.
     *
     * POST /api/save-fcm-token
     */
    public function saveToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        try {
            $user = $request->user();
            $user->fcm_token = $request->fcm_token;
            $user->save();

            Log::info('USER: FCM token updated.', [
                'user_id' => $user->id,
                'token' => $request->fcm_token
            ]);

            return response()->json([
                'success' => true,
                'message' => 'FCM token saved successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('FCM Error: Failed to save FCM token.', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to save FCM token.'
            ], 500);
        }
    }

    /**
     * Remove FCM token for the authenticated user (Logout).
     *
     * POST /api/remove-fcm-token
     */
    public function removeToken(Request $request)
    {
        try {
            $user = $request->user();
            $user->fcm_token = null;
            $user->save();

            Log::info('USER: FCM token removed (Logout).', [
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'FCM token removed successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('FCM Error: Failed to remove FCM token.', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove FCM token.'
            ], 500);
        }
    }

    /**
     * Send a notification to a specific user (Admin context).
     *
     * POST /api/send-notification
     */
    public function sendManualNotification(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'title' => 'required|string',
            'message' => 'required|string',
            'data' => 'nullable|array'
        ]);

        try {
            $user = User::findOrFail($request->user_id);

            if (!$user->fcm_token) {
                return response()->json([
                    'success' => false,
                    'message' => 'User does not have an FCM token registered.'
                ], 400);
            }

            $success = $this->fcmService->sendNotification(
                $user->fcm_token,
                $request->title,
                $request->message,
                $request->data ?? [],
                $user->id
            );

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Notification sent successfully.'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification via FCM.'
            ], 500);

        } catch (\Exception $e) {
            Log::error('FCM Error: Failed to send manual notification.', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while sending notification.'
            ], 500);
        }
    }
    /**
     * Check if the current user has an FCM token stored.
     */
    public function status(Request $request)
    {
        return response()->json([
            'has_token' => !empty($request->user()->fcm_token)
        ]);
    }
}
