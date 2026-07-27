<?php

namespace App\Livewire\Storefront\Auth;

use App\Enums\AuthContext;
use App\Enums\TwoFactorMethod;
use App\Models\User;
use App\Services\CartService;
use App\Services\DeviceGuard;
use App\Services\DeviceTrust;
use App\Services\OtpService;
use App\Support\PostLoginRedirect;
use App\Support\Totp;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Second step of the login: the password passed, the user is parked in
 * the session ('two_factor:user_id') and stays a guest until the code
 * (email / TOTP / recovery) checks out.
 */
#[Layout('layouts.landing', ['variant' => 'light'])]
class TwoFactorChallenge extends Component
{
    public string $code = '';

    public bool $useRecoveryCode = false;

    public string $recovery_code = '';

    /** "Trust this device for 30 days" — skip the code next time on this device. */
    public bool $trustDevice = false;

    public function mount(): void
    {
        if ($this->pendingUser() === null) {
            $this->abandon();
        }
    }

    public function verify(): void
    {
        $user = $this->pendingUser();

        if ($user === null) {
            $this->abandon();

            return;
        }

        $throttleKey = 'two-factor:'.$user->id.'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->addError('code', __('Too many attempts. Try again in :seconds seconds.', [
                'seconds' => RateLimiter::availableIn($throttleKey),
            ]));

            return;
        }

        if (! $this->attemptVerification($user)) {
            RateLimiter::hit($throttleKey);

            return;
        }

        RateLimiter::clear($throttleKey);

        $remember = (bool) session('two_factor:remember', false);
        $context = AuthContext::fromValue(session('two_factor:context'));
        session()->forget(['two_factor:user_id', 'two_factor:remember', 'two_factor:context']);

        Auth::loginUsingId($user->id, $remember);
        session()->regenerate();

        app(CartService::class)->mergeSessionCart($user);

        // Unseen device? Record it and alert (silent on the first ever login).
        app(DeviceGuard::class)->record($user, request());

        // If they asked, trust this device so the next login on it skips the
        // code. Runs after DeviceGuard::record so it reuses that same row.
        if ($this->trustDevice) {
            app(DeviceTrust::class)->remember($user, request());
        }

        // Role-aware: a stale intended URL must not walk an admin into the
        // seller application (or any section they can't enter).
        $this->redirect(PostLoginRedirect::url($user, $context), navigate: true);
    }

    private function attemptVerification(User $user): bool
    {
        if ($this->useRecoveryCode) {
            $this->validate(['recovery_code' => ['required', 'string']]);

            if (! $user->consumeRecoveryCode($this->recovery_code)) {
                $this->addError('recovery_code', __('That recovery code isn\'t valid — each code works once. Try another from your list.'));

                return false;
            }

            return true;
        }

        $this->validate(['code' => ['required', 'string']]);

        if ($user->two_factor_method === TwoFactorMethod::Totp) {
            if (! app(Totp::class)->verify((string) $user->two_factor_secret, $this->code)) {
                $this->addError('code', __('That code isn\'t right — open your authenticator app and enter the current code.'));

                return false;
            }

            // Totp::verify() checks a ±1 period window but has no memory of
            // its own — the same 6-digit code would otherwise work again for
            // as long as it stays inside that window (replay). There is no
            // 2FA DB column for this (outside this fix's surface), so the
            // memo lives in cache keyed by user + code, TTL'd comfortably
            // past the verification window.
            if (! $this->rememberTotpCodeUnused($user->id, $this->code)) {
                $this->addError('code', __('That code has already been used — wait for your authenticator app to show a new one.'));

                return false;
            }

            return true;
        }

        $otp = app(OtpService::class);

        if (! $otp->verify($user, OtpService::PURPOSE_2FA_EMAIL, trim($this->code))) {
            $this->addError('code', $otp->hasActiveCode($user, OtpService::PURPOSE_2FA_EMAIL)
                ? __('That code isn\'t right — check the latest email and try again.')
                : __('That code no longer works — request a new code and enter it within 10 minutes.'));

            return false;
        }

        return true;
    }

    public function resend(): void
    {
        $user = $this->pendingUser();

        if ($user === null) {
            $this->abandon();

            return;
        }

        if ($user->two_factor_method !== TwoFactorMethod::Email) {
            return;
        }

        $otp = app(OtpService::class);

        if (! $otp->issue($user, OtpService::PURPOSE_2FA_EMAIL)) {
            $this->addError('code', __('A code was sent moments ago — wait :seconds seconds before requesting another.', [
                'seconds' => max($otp->availableIn($user, OtpService::PURPOSE_2FA_EMAIL), 1),
            ]));

            return;
        }

        $this->resetErrorBag();
        $this->dispatch('toast', message: __('New code sent — check your email.'));
    }

    public function toggleRecovery(): void
    {
        $user = $this->pendingUser();

        if ($user === null) {
            $this->abandon();

            return;
        }

        // Recovery codes exist for authenticator (TOTP) users only.
        if ($user->two_factor_method !== TwoFactorMethod::Totp) {
            return;
        }

        $this->useRecoveryCode = ! $this->useRecoveryCode;
        $this->reset('code', 'recovery_code');
        $this->resetErrorBag();
    }

    /**
     * True the first time this exact code is seen for this user; false (and
     * memoized either way is a no-op) on replay. TTL of 120s comfortably
     * covers Totp::verify()'s ±1 period (30s) window plus clock drift.
     */
    private function rememberTotpCodeUnused(int $userId, string $code): bool
    {
        $key = 'totp-used:'.$userId.':'.preg_replace('/\s+/', '', $code);

        if (Cache::has($key)) {
            return false;
        }

        Cache::put($key, true, 120);

        return true;
    }

    private function pendingUser(): ?User
    {
        $id = session('two_factor:user_id');

        if ($id === null) {
            return null;
        }

        $user = User::find($id);

        if ($user === null || ! $user->hasTwoFactor() || $user->isSuspended()) {
            return null;
        }

        return $user;
    }

    private function abandon(): void
    {
        session()->forget(['two_factor:user_id', 'two_factor:remember']);

        $this->redirectRoute('login', navigate: true);
    }

    public function render()
    {
        $user = $this->pendingUser();

        return view('livewire.storefront.auth.two-factor-challenge', [
            'method' => $user?->two_factor_method,
            'maskedEmail' => $user ? $this->maskEmail($user->email) : '',
        ])->title(__('Two-factor check'));
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);

        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));

        return $visible.str_repeat('•', max(mb_strlen($local) - mb_strlen($visible), 1)).'@'.$domain;
    }
}
