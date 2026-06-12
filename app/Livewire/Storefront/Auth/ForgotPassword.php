<?php

namespace App\Livewire\Storefront\Auth;

use App\Services\Turnstile;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.storefront')]
class ForgotPassword extends Component
{
    public string $email = '';

    public ?string $turnstileToken = null;

    public ?string $status = null;

    public function sendResetLink(): void
    {
        $this->status = null;

        $this->validate([
            'email' => ['required', 'email'],
        ]);

        $key = 'forgot-password:'.Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', __('Too many requests. Try again in :seconds seconds.', [
                'seconds' => RateLimiter::availableIn($key),
            ]));

            return;
        }

        if (! app(Turnstile::class)->verify($this->turnstileToken, request()->ip())) {
            $this->addError('turnstileToken', __('We couldn\'t verify you\'re human — refresh the page and try again.'));

            return;
        }

        RateLimiter::hit($key);

        Password::sendResetLink(['email' => $this->email]);

        // Same message whether or not the account exists — no email enumeration.
        $this->status = __('If that email exists, a reset link is on the way.');
    }

    public function render()
    {
        return view('livewire.storefront.auth.forgot-password')->title(__('Forgot password'));
    }
}
