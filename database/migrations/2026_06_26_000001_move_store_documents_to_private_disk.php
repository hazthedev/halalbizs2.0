<?php

use App\Models\StoreDocument;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Security: KYC documents (IC + SSM) were stored on the public disk and served
 * via an unauthenticated, enumerable /storage/{id} URL. Move any existing ones
 * to the private disk so previously-uploaded PII stops being publicly reachable.
 * New uploads already go private (StoreDocument::registerMediaCollections).
 *
 * No-op when there are no public store-document media (e.g. fresh installs / CI).
 */
return new class extends Migration
{
    public function up(): void
    {
        Media::query()
            ->where('model_type', StoreDocument::class)
            ->where('collection_name', 'file')
            ->where('disk', 'public')
            ->get()
            ->each(function (Media $media): void {
                $path = $media->getPathRelativeToRoot(); // "{id}/{filename}"
                $public = Storage::disk('public');
                $private = Storage::disk('local');

                if ($public->exists($path)) {
                    $private->writeStream($path, $public->readStream($path));
                    $public->deleteDirectory(dirname($path)); // drop the "{id}/" folder
                }

                $media->disk = 'local';
                $media->save();
            });
    }

    public function down(): void
    {
        // One-way security migration — intentionally not reversible (never re-expose PII).
    }
};
