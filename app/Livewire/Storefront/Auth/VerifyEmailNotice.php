<?php

namespace App\Livewire\Storefront\Auth;

use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.storefront')]
class VerifyEmailNotice extends Component
{
    public ?string $status = null;

    public function mount(): void
    {
        if (auth()->user()->hasVerifiedEmail()) {
            $this->redirectRoute('home', navigate: true);
        }
    }

    public function resend(): void
    {
        $user = auth()->user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectRoute('home', navigate: true);

            return;
        }

        $key = 'verification-resend:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            $this->addError('resend', __('We just sent one — try again in :seconds seconds.', [
                'seconds' => RateLimiter::availableIn($key),
            ]));

            return;
        }

        RateLimiter::hit($key);

        $user->sendEmailVerificationNotification();

        $this->status = __('Verification email sent — check your inbox.');
    }

    public function render()
    {
        return view('livewire.storefront.auth.verify-email-notice')->title(__('Verify your email'));
    }
}
