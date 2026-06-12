<?php

namespace App\Livewire\Storefront\Auth;

use App\Services\CartService;
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
        session()->regenerate();

        app(CartService::class)->mergeSessionCart($user);

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
