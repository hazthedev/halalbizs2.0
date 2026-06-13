<?php

namespace App\Livewire\Storefront\Auth;

use App\Enums\TwoFactorMethod;
use App\Services\CartService;
use App\Services\DeviceGuard;
use App\Services\OtpService;
use App\Services\Turnstile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.storefront')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public ?string $turnstileToken = null;

    public function login(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = $this->throttleKey();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', __('Too many login attempts. Try again in :seconds seconds.', [
                'seconds' => RateLimiter::availableIn($key),
            ]));

            return;
        }

        if (! app(Turnstile::class)->verify($this->turnstileToken, request()->ip())) {
            $this->addError('turnstileToken', __('We couldn\'t verify you\'re human — refresh the page and try again.'));

            return;
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($key);

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

        // 2FA gate: password alone doesn't log you in. Park the attempt in
        // the session and finish on the challenge screen.
        if ($user->hasTwoFactor()) {
            Auth::logout();

            session()->put([
                'two_factor:user_id' => $user->id,
                'two_factor:remember' => $this->remember,
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

        $this->redirectIntended(route('home'), navigate: true);
    }

    private function throttleKey(): string
    {
        return 'login:'.Str::lower($this->email).'|'.request()->ip();
    }

    public function render()
    {
        return view('livewire.storefront.auth.login')->title(__('Log in'));
    }
}
