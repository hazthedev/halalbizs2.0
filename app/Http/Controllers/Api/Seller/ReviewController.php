<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Api\ApiResponse;
use App\Http\Resources\SellerReviewResource;
use App\Services\SellerReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Store review inbox (docs/09 §C): one reply per review, editable for 24h. */
class ReviewController extends SellerController
{
    public const PER_PAGE = 10;

    public function index(Request $request): JsonResponse
    {
        $reviews = $this->currentStore($request)->reviews()
            ->with(['product.media', 'user', 'media'])
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE);

        return ApiResponse::ok(SellerReviewResource::collection($reviews));
    }

    public function reply(Request $request, int $review, SellerReviewService $reviews): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'body.required' => __('Write your reply before posting it.'),
            'body.min' => __('Write at least 5 characters.'),
        ]);

        $row = $this->currentStore($request)->reviews()->with(['product.media', 'user', 'media'])->findOrFail($review);

        return $this->attempt(function () use ($row, $reviews, $data) {
            $reviews->reply($row, $data['body']);

            return ApiResponse::ok(new SellerReviewResource($row->fresh(['product.media', 'user', 'media'])));
        }, 'reply_locked');
    }
}
