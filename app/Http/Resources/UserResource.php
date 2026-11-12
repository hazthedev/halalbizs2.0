<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Services\CoinService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $store = $this->store;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'email_verified' => $this->hasVerifiedEmail(),
            'preferred_locale' => $this->preferred_locale,
            'roles' => $this->getRoleNames()->values(),
            'store' => $store === null ? null : [
                'id' => $store->id,
                'slug' => $store->slug,
                'name' => $store->name,
                'status' => $store->status->value,
            ],
            // Staff who may manage content can ask the API for drafts (`?preview=1`).
            'can_preview_content' => $this->can('cms.manage'),
            'unread_notifications' => $this->unreadNotifications()->count(),
            // Loyalty coins are a count, not money — no _sen suffix on purpose.
            'coins' => app(CoinService::class)->balance($this->resource),
        ];
    }
}
