<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Review */
class ReviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'body' => $this->comment,
            'buyer_name' => $this->reviewerDisplayName(),
            'photos' => $this->getMedia('photos')->map(fn ($media) => $media->getUrl('thumb'))->values()->all(),
            'created_at' => $this->created_at?->toIso8601String(),
            'seller_reply' => $this->seller_reply === null ? null : [
                'body' => $this->seller_reply,
                'at' => $this->seller_replied_at?->toIso8601String(),
            ],
        ];
    }
}
