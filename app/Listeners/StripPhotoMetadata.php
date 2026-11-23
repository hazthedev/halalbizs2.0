<?php

namespace App\Listeners;

use App\Support\PhotoMetadata;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Throwable;

/**
 * Every medialibrary upload (reviews, returns, products, variants, store
 * logo/banner, KYC and certificate images, CMS images) lands here right after
 * the original is written and before conversions are made from it, so no
 * stored original keeps its EXIF/GPS (HB-B2). Non-images are left alone.
 */
final class StripPhotoMetadata
{
    public function handle(MediaHasBeenAddedEvent $event): void
    {
        try {
            PhotoMetadata::stripMedia($event->media);
        } catch (Throwable $e) {
            // Undecodable image: keep the upload, log it; `media:strip-exif` counts it as an error.
            report($e);
        }
    }
}
