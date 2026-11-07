<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A review in the store's inbox. Load product.media, user and media first.
 *
 * @mixin Review
 */
class SellerReviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'body' => $this->comment,
            'buyer_name' => $this->reviewerDisplayName(),
            'product' => $this->product === null ? null : [
                'id' => $this->product->id,
                'name' => $this->product->getTranslation('name', app()->getLocale()),
                'thumb' => $this->product->getFirstMediaUrl('images', 'thumb') ?: null,
            ],
            'photos' => $this->getMedia('photos')->map(fn (Media $media) => $media->getUrl('thumb'))->values(),
            'is_hidden' => $this->is_hidden,
            'created_at' => $this->created_at?->toIso8601String(),
            'reply' => $this->seller_reply === null ? null : [
                'body' => $this->seller_reply,
                'at' => $this->seller_replied_at?->toIso8601String(),
            ],
            'can_reply' => ! $this->replyLocked(),
        ];
    }
}
