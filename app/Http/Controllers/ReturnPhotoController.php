<?php

namespace App\Http\Controllers;

use App\Models\ReturnRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Return evidence photos are private: only the buyer, the seller of that
 * sub-order's store, or an admin may open them (audit lead
 * api-return-photos-on-public-media-disk, gate NO 2026-11-17).
 */
class ReturnPhotoController extends Controller
{
    public function __invoke(Request $request, ReturnRequest $returnRequest, Media $media): StreamedResponse
    {
        abort_unless($media->model_type === $returnRequest->getMorphClass()
            && (int) $media->model_id === $returnRequest->id
            && $media->collection_name === 'photos', 404);

        $user = $request->user();
        $subOrder = $returnRequest->subOrder;
        $store = $subOrder->store;

        abort_unless(
            $user->id === $subOrder->order->user_id
            || ($store !== null && $store->user_id === $user->id && $store->isApproved())
            || $user->hasRole('admin'),
            403,
        );

        // Photos stored before the private disk keep their disk (public) until moved.
        return Storage::disk($media->disk)->response($media->getPathRelativeToRoot(), headers: [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
