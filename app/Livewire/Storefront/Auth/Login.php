<?php

namespace App\Livewire\Storefront\Auth;

use App\Enums\AuthContext;
use App\Enums\TwoFactorMethod;
use App\Services\CartService;
use App\Services\DeviceGuard;
use App\Services\DeviceTrust;
use App\Services\OtpService;
use App\Services\Turnstile;
use App\Support\PostLoginRedirect;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.landing', ['variant' => 'light'])]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public ?string $turnstileToken = null;

    /** Which door this login was reached through — reframes copy + landing only. */
    public string $context = '';

    public function mount(string $context = 'storefront'): void
    {
        $this->context = AuthContext::fromValue($context)->value;
    }

    public function login(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = $this->throttleKey();
        $ipKey = $this->ipThrottleKey();
        $emailKey = $this->emailThrottleKey();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', __('Too many login attempts. Try again in :seconds seconds.', [
                'seconds' => RateLimiter::availableIn($key),
            ]));

            return;
        }

        // Coarser, second limiter alongside the (email, IP) composite above:
        // that composite gives a fresh bucket per email, so spraying one
        // password across many addresses from one IP (or against one
        // address from many IPs) never trips it. This one does.
        if (RateLimiter::tooManyAttempts($ipKey, 20) || RateLimiter::tooManyAttempts($emailKey, 20)) {
            $seconds = max(RateLimiter::availableIn($ipKey), RateLimiter::availableIn($emailKey));

            $this->addError('email', __('Too many login attempts. Try again in :seconds seconds.', [
                'seconds' => $seconds,
            ]));

            return;
        }

        if (! app(Turnstile::class)->verify($this->turnstileToken, request()->ip())) {
            $this->addError('turnstileToken', __('We couldn\'t verify you\'re human — refresh the page and try again.'));

            return;
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($key);
            RateLimiter::hit($ipKey, 3600);
            RateLimiter::hit($emailKey, 3600);

            $this->addError('email', __('These details don\'t match our records — check your email and password.'));

            return;
        }

        $user = Auth::user();

        if ($user->isSuspended()) {
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();

            $this->addError('email', __('Your account has been suspended.'));

            return;
        }

        RateLimiter::clear($key);
        RateLimiter::clear($ipKey);
        RateLimiter::clear($emailKey);

        // 2FA gate: password alone doesn't log you in. Park the attempt in
        // the session and finish on the challenge screen — UNLESS this exact
        // device was trusted within the last 30 days, in which case the code
        // step is skipped (the password + the proven device stand in for it).
        if ($user->hasTwoFactor() && ! app(DeviceTrust::class)->isTrusted($user, request())) {
            Auth::logout();

            session()->put([
                'two_factor:user_id' => $user->id,
                'two_factor:remember' => $this->remember,
                // Carry the entry context across the challenge redirect so the
                // landing after 2FA matches the door they came through.
                'two_factor:context' => $this->context,
            ]);

            if ($user->two_factor_method === TwoFactorMethod::Email) {
                app(OtpService::class)->issue($user, OtpService::PURPOSE_2FA_EMAIL);
            }

            $this->redirectRoute('two-factor.challenge', navigate: true);

            return;
        }

        session()->regenerate();

        app(CartService::class)->mergeSessionCart($user);

        // Unseen device? Record it and alert (silent on the first ever login).
        app(DeviceGuard::class)->record($user, request());

        // Role-aware: a stale intended URL must not walk an admin into the
        // seller application (or any section they can't enter).
        $this->redirect(PostLoginRedirect::url($user, AuthContext::fromValue($this->context)), navigate: true);
    }

    private function throttleKey(): string
    {
        return 'login:'.Str::lower($this->email).'|'.request()->ip();
    }

    /** Per-IP bucket — catches one password sprayed across many emails. */
    private function ipThrottleKey(): string
    {
        return 'login-ip:'.request()->ip();
    }

    /** Per-email bucket regardless of IP — catches one email attacked from many IPs. */
    private function emailThrottleKey(): string
    {
        return 'login-email:'.Str::lower($this->email);
    }

    public function render()
    {
        return view('livewire.storefront.auth.login', [
            'ctx' => AuthContext::fromValue($this->context),
        ])->title(AuthContext::fromValue($this->context)->loginTitle());
    }
}
