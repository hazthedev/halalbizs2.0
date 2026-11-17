<?php

namespace App\Http\Controllers\Api;

use App\Http\Api\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * FCM registration tokens (mobile plan §5). Upsert on the token itself: the
 * same install re-registering (or switching accounts) keeps one row.
 */
class DeviceController extends Controller
{
    private const MAX_PER_USER = 10;

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['required', Rule::in(['ios', 'android'])],
        ]);

        $device = DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            ['user_id' => $request->user()->id, 'platform' => $data['platform'], 'last_seen_at' => now()],
        );

        // At most MAX_PER_USER installs per account, newest kept: unbounded junk
        // tokens would make every notification a long serial FCM loop.
        $request->user()->deviceTokens()
            ->orderByDesc('last_seen_at')->orderByDesc('id')
            ->skip(self::MAX_PER_USER)->take(PHP_INT_MAX)->pluck('id')
            ->whenNotEmpty(fn ($stale) => DeviceToken::whereKey($stale)->delete());

        return ApiResponse::ok(['id' => $device->id, 'platform' => $device->platform], null, 201);
    }

    public function destroy(Request $request, string $token): JsonResponse
    {
        $request->user()->deviceTokens()->where('token', $token)->delete();

        return ApiResponse::ok(null);
    }
}
