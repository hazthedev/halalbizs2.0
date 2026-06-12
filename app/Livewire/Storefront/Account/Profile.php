<?php

namespace App\Livewire\Storefront\Account;

use App\Settings\GeneralSettings;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.storefront')]
class Profile extends Component
{
    public string $name = '';

    public ?string $phone = null;

    public string $preferred_locale = 'en';

    public string $preferred_currency = 'MYR';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = auth()->user();

        $this->name = $user->name;
        $this->phone = $user->phone;
        $this->preferred_locale = $user->preferred_locale ?? 'en';
        $this->preferred_currency = $user->preferred_currency ?? 'MYR';
    }

    public function updateProfile(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'preferred_locale' => ['required', Rule::in(app(GeneralSettings::class)->enabled_locales)],
            'preferred_currency' => ['required', Rule::in(app(GeneralSettings::class)->display_currencies)],
        ]);

        $data['phone'] = $data['phone'] ?: null;

        auth()->user()->update($data);

        $this->dispatch('toast', message: __('Saved'));
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'current_password.current_password' => __('That doesn\'t match your current password — try again.'),
        ]);

        auth()->user()->update(['password' => $this->password]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('toast', message: __('Password updated'));
    }

    public function render()
    {
        return view('livewire.storefront.account.profile', [
            'locales' => app(GeneralSettings::class)->enabled_locales,
            'currencies' => app(GeneralSettings::class)->display_currencies,
        ])->title(__('Profile'));
    }
}
