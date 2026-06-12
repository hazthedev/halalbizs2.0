<?php

namespace App\Livewire\Storefront\Account;

use App\Support\MalaysianStates;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.storefront')]
class Addresses extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public ?string $label = null;

    public string $recipient_name = '';

    public string $phone = '';

    public string $line1 = '';

    public ?string $line2 = null;

    public string $postcode = '';

    public string $city = '';

    public string $state = '';

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $addressId): void
    {
        $address = auth()->user()->addresses()->findOrFail($addressId);

        $this->resetForm();

        $this->editingId = $address->id;
        $this->label = $address->label;
        $this->recipient_name = $address->recipient_name;
        $this->phone = $address->phone;
        $this->line1 = $address->line1;
        $this->line2 = $address->line2;
        $this->postcode = $address->postcode;
        $this->city = $address->city;
        $this->state = $address->state;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'label' => ['nullable', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'postcode' => ['required', 'digits:5'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', Rule::in(MalaysianStates::ALL)],
        ], [
            'postcode.digits' => __('Postcode must be exactly 5 digits, like 50450.'),
        ]);

        $user = auth()->user();

        $data['label'] = $data['label'] ?: null;
        $data['line2'] = $data['line2'] ?: null;
        $data['country'] = 'MY';

        if ($this->editingId !== null) {
            $user->addresses()->findOrFail($this->editingId)->update($data);

            $this->dispatch('toast', message: __('Address updated'));
        } else {
            // First address becomes the default automatically.
            $data['is_default'] = $user->addresses()->doesntExist();

            $user->addresses()->create($data);

            $this->dispatch('toast', message: __('Address added'));
        }

        $this->resetForm();
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function setDefault(int $addressId): void
    {
        auth()->user()->addresses()->findOrFail($addressId)->update(['is_default' => true]);

        $this->dispatch('toast', message: __('Default address updated'));
    }

    public function delete(int $addressId): void
    {
        $user = auth()->user();
        $address = $user->addresses()->findOrFail($addressId);

        if ($address->is_default && $user->addresses()->count() > 1) {
            $this->dispatch('toast', message: __('Set another address as default before deleting this one.'), type: 'error');

            return;
        }

        $address->delete();

        $this->dispatch('toast', message: __('Address deleted'));
    }

    private function resetForm(): void
    {
        $this->reset('showForm', 'editingId', 'label', 'recipient_name', 'phone', 'line1', 'line2', 'postcode', 'city', 'state');
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.storefront.account.addresses', [
            'addresses' => auth()->user()->addresses()->orderByDesc('is_default')->latest()->get(),
            'states' => MalaysianStates::ALL,
        ])->title(__('Addresses'));
    }
}
