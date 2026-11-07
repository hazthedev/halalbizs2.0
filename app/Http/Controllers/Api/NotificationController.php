<?php

namespace App\Http\Controllers\Api;

use App\Http\Api\ApiResponse;
use App\Http\Controllers\Controller;
use App\Support\AppLinks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/** Database notifications as the web Notifications page lists them, with the app route beside the web URL. */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->latest()->paginate(20);

        $notifications->setCollection($notifications->getCollection()->map(fn (DatabaseNotification $notification) => [
            'id' => $notification->id,
            'type' => class_basename($notification->type),
            'message' => $notification->data['message'] ?? null,
            'detail' => $notification->data['detail'] ?? null,
            'url' => $notification->data['url'] ?? null,
            'app_url' => AppLinks::fromWebUrl($notification->data['url'] ?? null),
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ]));

        return ApiResponse::ok($notifications, ['unread' => $request->user()->unreadNotifications()->count()]);
    }

    public function read(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->findOrFail($id)->markAsRead();

        return ApiResponse::ok(null);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return ApiResponse::ok(null);
    }
}
