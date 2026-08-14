<?php

namespace App\Livewire\Storefront\Auth;

use App\Services\DeviceTrust;
use App\Support\ClientIp;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.landing', ['variant' => 'light'])]
class ResetPassword extends Component
{
    #[Locked]
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        // M-3: the only one of the six auth components with no limiter, and
        // neither its route nor /livewire/update supplies one. Keyed the same
        // way ForgotPassword does — on the account AND the address, since a
        // reset token is guessable-in-principle and this is the redemption door.
        $key = 'reset-password:'.Str::lower($this->email).'|'.ClientIp::bucket();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', __('Too many attempts. Try again in :seconds seconds.', [
                'seconds' => RateLimiter::availableIn($key),
            ]));

            return;
        }

        RateLimiter::hit($key);

        $status = Password::reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function ($user) {
                $user->forceFill([
                    'password' => $this->password,
                    'remember_token' => Str::random(60),
                ])->save();

                // A reset means the old password may be compromised — drop every
                // trusted device so 2FA is required again everywhere.
                app(DeviceTrust::class)->forgetAll($user);

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->addError('email', __($status));

            return;
        }

        session()->flash('status', __('Password updated — log in with your new password.'));

        $this->redirectRoute('login', navigate: true);
    }

    public function render()
    {
        return view('livewire.storefront.auth.reset-password')->title(__('Reset password'));
    }
}
