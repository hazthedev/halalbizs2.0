<?php

namespace App\Livewire\Storefront\Auth;

use App\Enums\AuthContext;
use App\Models\User;
use App\Services\CartService;
use App\Services\Turnstile;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.landing', ['variant' => 'light'])]
class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public ?string $phone = null;

    public bool $terms = false;

    public ?string $turnstileToken = null;

    /** Which door this registration was reached through — reframes copy + next step. */
    public string $context = '';

    public function mount(string $context = 'storefront'): void
    {
        $this->context = AuthContext::fromValue($context)->value;
    }

    public function register(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:30'],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => __('Please agree to the terms and privacy policy to create an account.'),
        ]);

        if (! app(Turnstile::class)->verify($this->turnstileToken, request()->ip())) {
            $this->addError('turnstileToken', __('We couldn\'t verify you\'re human — refresh the page and try again.'));

            return;
        }

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'phone' => $this->phone ?: null,
        ]);

        $user->assignRole('buyer');

        event(new Registered($user));

        Auth::login($user);
        session()->regenerate();

        app(CartService::class)->mergeSessionCart($user);

        // A seller-context signup is "make an account, then apply to sell" —
        // park the application as the intended destination so email
        // verification lands them there instead of the storefront.
        if (AuthContext::fromValue($this->context) === AuthContext::Seller) {
            session()->put('url.intended', route('seller.apply'));
        }

        $this->redirectRoute('verification.notice', navigate: true);
    }

    public function render()
    {
        return view('livewire.storefront.auth.register', [
            'ctx' => AuthContext::fromValue($this->context),
        ])->title(__('Create account'));
    }
}
