<?php

namespace App\Http\Controllers\Api;

use App\Enums\TwoFactorMethod;
use App\Http\Api\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\DeviceGuard;
use App\Services\OtpService;
use App\Services\TwoFactorVerifier;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * Token auth for the mobile app (mobile plan §4). Same credentials, password
 * rules, suspension refusal and second factor as the web Login / Register /
 * TwoFactorChallenge components; Turnstile is web-only and the rate limiters
 * on the routes stand in for it.
 */
class AuthController extends Controller
{
    private const CHALLENGE_TTL_MINUTES = 10;

    private const CHALLENGE_MAX_ATTEMPTS = 5;

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
            'phone' => ['nullable', 'string', 'max:30'],
            'device_name' => ['required', 'string', 'max:100'],
        ], [
            'password.confirmed' => __('Both password fields need to match.'),
            'email.unique' => __('That email already has an account — log in instead.'),
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
        ]);

        $user->assignRole('buyer');

        event(new Registered($user));

        return $this->issueToken($user, $data['device_name'], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        // Auth::validate() walks the same provider + hasher as the web's
        // Auth::attempt(), without starting a session.
        if (! Auth::guard('web')->validate(['email' => $data['email'], 'password' => $data['password']])) {
            throw ValidationException::withMessages([
                'email' => __('These details don\'t match our records — check your email and password.'),
            ]);
        }

        /** @var User $user */
        $user = Auth::guard('web')->getLastAttempted();

        if ($user->isSuspended()) {
            throw ValidationException::withMessages(['email' => __('Your account has been suspended.')]);
        }

        if ($user->hasTwoFactor()) {
            $challenge = (string) Str::uuid();

            Cache::put('api:2fa:'.$challenge, ['user_id' => $user->id, 'attempts' => 0], now()->addMinutes(self::CHALLENGE_TTL_MINUTES));

            if ($user->two_factor_method === TwoFactorMethod::Email) {
                app(OtpService::class)->issue($user, OtpService::PURPOSE_2FA_EMAIL);
            }

            return ApiResponse::ok([
                'two_factor' => ['challenge' => $challenge, 'method' => $user->two_factor_method->value],
            ], null, 202);
        }

        app(DeviceGuard::class)->record($user, $request);

        return $this->issueToken($user, $data['device_name']);
    }

    public function twoFactor(Request $request, TwoFactorVerifier $verifier): JsonResponse
    {
        $data = $request->validate([
            'challenge' => ['required', 'uuid'],
            'code' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $key = 'api:2fa:'.$data['challenge'];
        $pending = Cache::get($key);
        $user = is_array($pending) ? User::find($pending['user_id']) : null;

        if ($user === null || ! $user->hasTwoFactor() || $user->isSuspended()) {
            Cache::forget($key);

            throw ValidationException::withMessages([
                'challenge' => __('That login attempt has expired — log in again to start a new check.'),
            ]);
        }

        if (($error = $verifier->check($user, $data['code'])) !== null) {
            // Five misses end the challenge; the next try starts from the
            // password again, behind the login limiter.
            if (++$pending['attempts'] >= self::CHALLENGE_MAX_ATTEMPTS) {
                Cache::forget($key);
            } else {
                Cache::put($key, $pending, now()->addMinutes(self::CHALLENGE_TTL_MINUTES));
            }

            throw ValidationException::withMessages(['code' => $error]);
        }

        Cache::forget($key);

        app(DeviceGuard::class)->record($user, $request);

        return $this->issueToken($user, $data['device_name']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::ok(null);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink(['email' => $data['email']]);

        // Same answer whether or not the account exists — no email enumeration.
        return ApiResponse::ok(['message' => __('If that email exists, a reset link is on the way.')]);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return ApiResponse::ok(['verified' => $user->hasVerifiedEmail()]);
    }

    private function issueToken(User $user, string $deviceName, int $status = 200): JsonResponse
    {
        return ApiResponse::ok([
            'token' => $user->createToken($deviceName)->plainTextToken,
            'user' => (new UserResource($user))->resolve(),
        ], null, $status);
    }
}
