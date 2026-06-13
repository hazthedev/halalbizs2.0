<?php

namespace App\Http\Controllers;

use App\Enums\TwoFactorMethod;
use App\Models\User;
use App\Services\CartService;
use App\Services\DeviceGuard;
use App\Services\OtpService;
use App\Settings\SecuritySettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/**
 * Google OAuth via Socialite. Credentials live in admin settings (not env)
 * so the panel controls the rollout — both endpoints 404 while unconfigured,
 * and config is hydrated from settings at request time.
 */
class GoogleAuthController extends Controller
{
    public function redirect(SecuritySettings $settings)
    {
        abort_unless($settings->googleEnabled(), 404);

        $this->configureGoogle($settings);

        return Socialite::driver('google')->redirect();
    }

    public function callback(SecuritySettings $settings, CartService $cart, OtpService $otp)
    {
        abort_unless($settings->googleEnabled(), 404);

        $this->configureGoogle($settings);

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return redirect()->route('login')
                ->with('status', __('Google sign-in didn\'t complete — try again or log in with your password.'));
        }

        $user = User::query()->where('google_id', $googleUser->getId())->first()
            ?? User::query()->where('email', $googleUser->getEmail())->first();

        if ($user === null) {
            $user = User::create([
                'name' => $googleUser->getName() ?: ($googleUser->getNickname() ?: __('HalalBizs shopper')),
                'email' => $googleUser->getEmail(),
                'password' => Str::password(40),
                'google_id' => $googleUser->getId(),
            ]);

            // Google already verified this address.
            $user->forceFill(['email_verified_at' => now()])->save();

            $user->assignRole('buyer');
        } else {
            if ($user->isSuspended()) {
                return redirect()->route('login')
                    ->with('status', __('Your account has been suspended.'));
            }

            $user->forceFill([
                'google_id' => $user->google_id ?? $googleUser->getId(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        }

        // 2FA-enabled accounts still face the challenge — Google replaces
        // the password step only.
        if ($user->hasTwoFactor()) {
            session()->put([
                'two_factor:user_id' => $user->id,
                'two_factor:remember' => false,
            ]);

            if ($user->two_factor_method === TwoFactorMethod::Email) {
                $otp->issue($user, OtpService::PURPOSE_2FA_EMAIL);
            }

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user);
        session()->regenerate();

        $cart->mergeSessionCart($user);

        // Unseen device? Record it and alert (silent on the first ever login).
        app(DeviceGuard::class)->record($user, request());

        return redirect()->intended(route('home'));
    }

    private function configureGoogle(SecuritySettings $settings): void
    {
        config([
            'services.google.client_id' => $settings->google_client_id,
            'services.google.client_secret' => $settings->google_client_secret,
            'services.google.redirect' => route('auth.google.callback'),
        ]);
    }
}
