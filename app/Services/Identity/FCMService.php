<?php

namespace App\Services\Identity;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\HttpHandler\HttpHandlerFactory;

class FCMService
{
    protected $projectId;
    protected $credentialsPath;

    public function __construct()
    {
        $this->projectId = 'foodhub-2501a'; // Project ID from your JSON
        $this->credentialsPath = storage_path('app/firebase-service-account.json');
    }

    /**
     * Get OAuth2 Access Token for FCM V1
     */
    private function getAccessToken()
    {
        try {
            $credentials = new ServiceAccountCredentials(
                'https://www.googleapis.com/auth/cloud-platform',
                $this->credentialsPath
            );

            $token = $credentials->fetchAuthToken(HttpHandlerFactory::build());
            return $token['access_token'] ?? null;
        } catch (\Exception $e) {
            Log::error('FCM V1: Failed to fetch access token', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Send a push notification to a specific FCM token (V1 API).
     */
    public function sendNotification(string $token, string $title, string $body, array $data = [], int $userId = null)
    {
        if (!$token) {
            Log::warning('FCM V1: Attempted to send notification to null token.');
            return false;
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) return false;

        try {
            $payload = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                        'image' => 'https://apnacart-backend-2alg.onrender.com/assets/logo.png',
                    ],
                    'data' => count($data) > 0 ? array_map('strval', $data) : (object)[],
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'sound' => 'default',
                            'icon' => 'notification_icon',
                            'color' => '#ff4c1a',
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            'channel_id' => 'fcm_default_channel',
                        ],
                    ],
                ],
            ];

            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", $payload);

            $success = $response->successful();
            
            if (!$success) {
                Log::error('FCM V1 Error', [
                    'user_id' => $userId,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }

            if ($userId) {
                \App\Models\NotificationHistory::create([
                    'user_id' => $userId,
                    'title' => $title,
                    'message' => $body,
                    'data' => $data,
                    'status' => $success ? 'sent' : 'failed',
                    'error_message' => $success ? null : $response->body()
                ]);
            }

            return $success;
        } catch (\Exception $e) {
            Log::error('FCM V1 Exception', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send a notification to multiple user IDs.
     */
    public function sendToUsers(array $userIds, string $title, string $body, array $data = [])
    {
        $users = \App\Models\User::whereIn('id', $userIds)->whereNotNull('fcm_token')->get();
        $results = ['success' => 0, 'failure' => 0, 'skipped' => count($userIds) - $users->count()];

        foreach ($users as $user) {
            if ($this->sendNotification($user->fcm_token, $title, $body, $data, $user->id)) {
                $results['success']++;
            } else {
                $results['failure']++;
            }
        }
        return $results;
    }

    /**
     * Broadcast a data-only (silent) notification to all users.
     */
    public function broadcastData(array $data)
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) return 0;

        $tokens = \App\Models\User::whereNotNull('fcm_token')->pluck('fcm_token')->toArray();
        $count = 0;

        foreach ($tokens as $token) {
            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", [
                    'message' => [
                        'token' => $token,
                        'data' => count($data) > 0 ? array_map('strval', $data) : (object)[],
                        'android' => [
                            'priority' => 'high',
                        ],
                    ],
                ]);
            if ($response->successful()) $count++;
        }

        return $count;
    }
}
