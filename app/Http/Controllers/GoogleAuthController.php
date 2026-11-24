<?php

namespace App\Http\Controllers;

use App\Enums\TwoFactorMethod;
use App\Models\User;
use App\Services\CartService;
use App\Services\DeviceGuard;
use App\Services\DeviceTrust;
use App\Services\OtpService;
use App\Settings\SecuritySettings;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        return $this->signIn([
            'id' => $googleUser->getId(),
            'email' => $googleUser->getEmail(),
            'name' => $googleUser->getName() ?: ($googleUser->getNickname() ?: __('HalalBizs shopper')),
            'email_verified' => ($googleUser->user['email_verified'] ?? false) === true,
        ], consented: false, cart: $cart, otp: $otp);
    }

    /**
     * Consent step for a NEW Google account (PDPA, HB-G221): nothing is created
     * until the person ticks the same terms + privacy box as web register.
     * The verified Google profile waits in the session meanwhile.
     */
    public function consent(SecuritySettings $settings)
    {
        abort_unless($settings->googleEnabled(), 404);

        $pending = session('google_signup');

        if (! is_array($pending)) {
            return redirect()->route('login');
        }

        return view('auth.google-consent', ['email' => $pending['email']]);
    }

    public function storeConsent(Request $request, SecuritySettings $settings, CartService $cart, OtpService $otp)
    {
        abort_unless($settings->googleEnabled(), 404);

        if (! is_array(session('google_signup'))) {
            return redirect()->route('login');
        }

        $request->validate(['terms' => ['accepted']], [
            'terms.accepted' => __('Please agree to the terms and privacy policy to create an account.'),
        ]);

        return $this->signIn(session()->pull('google_signup'), consented: true, cart: $cart, otp: $otp);
    }

    /**
     * @param  array{id: string, email: string, name: string, email_verified: bool}  $google
     */
    private function signIn(array $google, bool $consented, CartService $cart, OtpService $otp)
    {
        // Re-run on the consent POST too: an account may have appeared for this
        // email meanwhile, and it is linked exactly as the callback would.
        $user = User::query()->where('google_id', $google['id'])->first();

        // No google_id match yet — fall back to an email match, but ONLY when
        // Google itself vouches the address is verified. Without that check,
        // anyone who registers an unverified Google account on someone else's
        // email address silently takes over that existing HalalBizs account.
        if ($user === null && $google['email_verified']) {
            $user = User::query()->where('email', $google['email'])->first();
        } elseif ($user === null) {
            $existing = User::query()->where('email', $google['email'])->first();

            if ($existing !== null) {
                return redirect()->route('login')
                    ->with('status', __('That email is already registered — log in with your password, then link Google from your account settings.'));
            }
        }

        if ($user === null && ! $consented) {
            session()->put('google_signup', $google);

            return redirect()->route('auth.google.consent');
        }

        if ($user === null) {
            $user = User::create([
                'name' => $google['name'],
                'email' => $google['email'],
                'password' => Str::password(40),
                'google_id' => $google['id'],
                'privacy_consented_at' => now(),
                'privacy_notice_version' => config('app.privacy_notice_version'),
            ]);

            $user->assignRole('buyer');

            // Verified only when Google itself says so (HB-G227); otherwise
            // the normal verification email goes out, as on web register.
            if ($google['email_verified']) {
                $user->forceFill(['email_verified_at' => now()])->save();
            } else {
                event(new Registered($user));
            }
        } else {
            if ($user->isSuspended()) {
                return redirect()->route('login')
                    ->with('status', __('Your account has been suspended.'));
            }

            // Never-verified account: whoever registered it may not own the
            // email (API register hands out a token first). Google just proved
            // the owner, so drop every credential the earlier registrant holds,
            // their Google link included (it is set to this Google id below).
            if ($user->email_verified_at === null && $google['email_verified']) {
                $user->forceFill([
                    'google_id' => null,
                    'password' => Str::password(40),
                    'password_set_at' => null,
                    'two_factor_method' => null,
                    'two_factor_secret' => null,
                    'two_factor_recovery_codes' => null,
                ]);
                $user->revokeAppAccess();
                app(DeviceTrust::class)->forgetAll($user);

                if (config('session.driver') === 'database') {
                    DB::connection(config('session.connection'))
                        ->table(config('session.table', 'sessions'))
                        ->where('user_id', $user->id)->delete();
                }
            }

            $user->forceFill([
                'google_id' => $user->google_id ?? $google['id'],
                'email_verified_at' => $user->email_verified_at ?? ($google['email_verified'] ? now() : null),
            ])->save();
        }

        // 2FA-enabled accounts still face the challenge — Google replaces
        // the password step only. So do admins/sellers on a new device.
        $newDevice = ! $user->hasTwoFactor() && app(DeviceGuard::class)->needsCode($user, request());

        if ($newDevice || $user->hasTwoFactor()) {
            session()->put([
                'two_factor:user_id' => $user->id,
                'two_factor:remember' => false,
                'two_factor:new_device' => $newDevice,
            ]);

            if ($newDevice || $user->two_factor_method === TwoFactorMethod::Email) {
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
