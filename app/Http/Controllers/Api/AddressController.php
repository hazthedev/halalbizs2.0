<?php

namespace App\Http\Controllers\Api;

use App\Http\Api\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The buyer's address book (mobile plan §4). Every lookup goes through the
 * user's own relation, so another user's address id is simply a 404.
 */
class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()->addresses()->orderByDesc('is_default')->latest()->get();

        return ApiResponse::ok(AddressResource::collection($addresses));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $user = $request->user();

        // First address becomes the default automatically.
        $data['is_default'] = $data['is_default'] ?? $user->addresses()->doesntExist();

        $address = $user->addresses()->create($data);

        return ApiResponse::ok(new AddressResource($address), null, 201);
    }

    public function update(Request $request, int $address): JsonResponse
    {
        $row = $request->user()->addresses()->findOrFail($address);

        $row->update($this->validated($request));

        return ApiResponse::ok(new AddressResource($row->fresh()));
    }

    public function destroy(Request $request, int $address): JsonResponse
    {
        $user = $request->user();
        $row = $user->addresses()->findOrFail($address);

        if ($row->is_default && $user->addresses()->count() > 1) {
            return ApiResponse::error('address_is_default', __('Set another address as default before deleting this one.'), 409);
        }

        $row->delete();

        return ApiResponse::ok(null);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate(Address::rules() + ['is_default' => ['sometimes', 'boolean']], [
            'postcode.digits' => __('Postcode must be exactly 5 digits, like 50450.'),
        ]);

        // "Not default" is not a state you can set: unflagging the current
        // default would leave the book with none. Flag another one instead.
        if (($data['is_default'] ?? null) === false) {
            unset($data['is_default']);
        }

        $data['label'] = ($data['label'] ?? null) ?: null;
        $data['line2'] = ($data['line2'] ?? null) ?: null;
        $data['country'] = 'MY';

        return $data;
    }
}
