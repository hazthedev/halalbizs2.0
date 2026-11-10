<?php

namespace App\Http\Controllers\Api;

use App\Http\Api\ApiResponse;
use App\Http\Controllers\AppWebSignInController;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\AccountDeletionService;
use App\Settings\GeneralSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * The signed-in user's own profile (mobile plan §4). Same rules as the web
 * Account/Profile component, minus the browser-only bits.
 */
class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::ok(new UserResource($request->user()));
    }

    public function update(Request $request, GeneralSettings $settings): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'preferred_locale' => ['sometimes', 'required', Rule::in($settings->enabled_locales)],
        ]);

        $user = $request->user();

        if (array_key_exists('phone', $data)) {
            $data['phone'] = $data['phone'] ?: null;

            // A different number is a different phone — verification resets.
            if ($data['phone'] !== $user->phone) {
                $data['phone_verified_at'] = null;
            }
        }

        $user->update($data);

        return ApiResponse::ok(new UserResource($user->fresh()));
    }

    public function password(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'current_password.current_password' => __('That doesn\'t match your current password — try again.'),
        ]);

        $user = $request->user();
        $device = $user->currentAccessToken()->name;
        $user->update(['password' => $data['password']]);

        // Every token dies, this one included (it may have been copied). The
        // device that changed the password continues on a fresh token.
        $user->tokens()->delete();

        return ApiResponse::ok(['token' => $user->createToken($device)->plainTextToken]);
    }

    /**
     * Apple 5.1.1(v) / Google Play account deletion in-app: the web's PDPA
     * flow (which also revokes every token and device token).
     */
    public function destroy(Request $request, AccountDeletionService $deletion): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ], [
            'password.current_password' => __('That doesn\'t match your current password — try again.'),
        ]);

        $user = $request->user();

        if (($blocker = $deletion->blocker($user)) !== null) {
            return ApiResponse::error('account_deletion_blocked', $blocker, 409);
        }

        $deletion->delete($user);

        return ApiResponse::ok(null);
    }

    /** A 5-minute, single-use URL that signs this user into the web and opens `to` (AppWebSignInController). */
    public function webSignInLink(Request $request): JsonResponse
    {
        $to = $request->validate(['to' => ['required', Rule::in(array_keys(AppWebSignInController::DESTINATIONS))]])['to'];

        return ApiResponse::ok(AppWebSignInController::issue($request->user(), $to));
    }
}
