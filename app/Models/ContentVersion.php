<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One saved state of a piece of admin content (see App\Services\ContentDrafts).
 * status `draft` = waiting for a publisher, at most one per item;
 * status `published` = what went live and when (the history).
 *
 * A draft also holds the pictures and video uploaded with it, under the same
 * collection names as its subject; publishing moves them onto the subject.
 */
class ContentVersion extends Model implements HasMedia
{
    use InteractsWithMedia;

    /** Collections a subject may have (Banner: image, image_vi, video; ThemeAsset: image). */
    public const MEDIA = ['image', 'image_vi', 'video'];

    protected $guarded = [];

    public function registerMediaCollections(): void
    {
        foreach (self::MEDIA as $collection) {
            $this->addMediaCollection($collection)->singleFile();
        }
    }

    /** Same copies the subjects make, so a preview draws like the live page. */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('card')->width(1600)->performOnCollections('image', 'image_vi');
        $this->addMediaConversion('small')->width(800)->format('webp')->performOnCollections('image', 'image_vi');
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
