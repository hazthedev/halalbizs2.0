<?php

namespace App\Notifications\Channels;

use App\Models\DeviceToken;
use App\Support\AppLinks;
use Illuminate\Http\Client\Response;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Firebase Cloud Messaging, HTTP v1 (mobile plan §5). One message per
 * device token of the notifiable; title is the app name, body is the
 * notification's `message`, and `data.url` is the app route for its web URL.
 *
 * Auth is a service-account JWT signed with openssl (RS256) and exchanged for
 * an access token at Google's OAuth endpoint — no SDK (hard rule 10). When
 * FCM_CREDENTIALS is unset the channel is a silent no-op.
 */
class FcmChannel
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public function send(object $notifiable, Notification $notification): void
    {
        $credentials = $this->credentials();

        if ($credentials === null || ! method_exists($notifiable, 'deviceTokens')) {
            return;
        }

        $tokens = $notifiable->deviceTokens()->pluck('token');

        if ($tokens->isEmpty()) {
            return;
        }

        $data = method_exists($notification, 'toDatabase')
            ? $notification->toDatabase($notifiable)
            : (method_exists($notification, 'toArray') ? $notification->toArray($notifiable) : []);

        $body = (string) ($data['message'] ?? '');

        if ($body === '') {
            return;
        }

        $message = [
            'notification' => ['title' => config('app.name'), 'body' => $body],
            // FCM data values must be strings; a null route is simply omitted.
            'data' => array_filter(['url' => AppLinks::fromWebUrl($data['url'] ?? null)]),
        ];

        $accessToken = $this->accessToken($credentials);
        $endpoint = "https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send";

        foreach ($tokens as $token) {
            $response = Http::withToken($accessToken)->timeout(10)->post($endpoint, [
                'message' => $message + ['token' => $token],
            ]);

            if ($this->unregistered($response)) {
                DeviceToken::where('token', $token)->delete();
            } elseif (! $response->successful()) {
                Log::warning('FCM push failed', ['status' => $response->status(), 'body' => $response->body()]);
            }
        }
    }

    /** @return array{project_id: string, client_email: string, private_key: string}|null */
    private function credentials(): ?array
    {
        $path = config('services.fcm.credentials');

        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);

        if (! is_array($json) || empty($json['project_id']) || empty($json['client_email']) || empty($json['private_key'])) {
            return null;
        }

        return $json;
    }

    /**
     * Google access token for the service account, cached for 50 of its 60
     * minutes. Shared across workers through the app cache.
     *
     * @param  array{project_id: string, client_email: string, private_key: string}  $credentials
     */
    private function accessToken(array $credentials): string
    {
        $cacheKey = 'fcm:access_token:'.sha1($credentials['client_email']);

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($credentials) {
            $response = Http::asForm()->timeout(10)->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->jwt($credentials),
            ]);

            $token = $response->json('access_token');

            if (! is_string($token) || $token === '') {
                throw new RuntimeException('FCM: could not obtain an access token ('.$response->status().').');
            }

            return $token;
        });
    }

    /** @param  array{client_email: string, private_key: string}  $credentials */
    private function jwt(array $credentials): string
    {
        $now = time();

        $segments = [
            $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            $this->base64Url(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => self::TOKEN_URL,
                'iat' => $now,
                'exp' => $now + 3600,
            ])),
        ];

        $signature = '';

        if (! openssl_sign(implode('.', $segments), $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('FCM: could not sign the service-account JWT.');
        }

        $segments[] = $this->base64Url($signature);

        return implode('.', $segments);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /** FCM says the token is dead: 404, or an UNREGISTERED error detail. */
    private function unregistered(Response $response): bool
    {
        return $response->status() === 404
            || str_contains((string) $response->body(), 'UNREGISTERED');
    }
}
