<?php

namespace App\Livewire\Seller;

use App\Livewire\Concerns\CurrentStore;
use App\Support\MalaysianBanks;
use App\Support\MalaysianStates;
use App\Support\RinggitInput;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Shop settings (docs/07 §A5) — profile, holiday mode, shipping (flat |
 * state matrix, all fees stored as integer sen), bank details.
 */
#[Layout('layouts.seller')]
class Settings extends Component
{
    use CurrentStore, WithFileUploads;

    // ----- Profile -----
    public string $description = '';

    public string $state = '';

    public ?TemporaryUploadedFile $logo = null;

    public ?TemporaryUploadedFile $banner = null;

    // ----- Holiday mode -----
    public bool $holidayMode = false;

    // ----- Shipping -----
    public string $shippingMode = 'flat';

    public string $flatFee = '';

    /** @var array<int, string> RM inputs aligned to MalaysianStates::ALL by index. */
    public array $matrix = [];

    public string $applyAll = '';

    public string $freeOver = '';

    // ----- Bank details -----
    public string $bankName = '';

    public string $accountName = '';

    public string $accountNumber = '';

    public function mount(): void
    {
        $store = $this->currentStore();

        $this->description = (string) $store->description;
        $this->state = (string) $store->state;
        $this->holidayMode = $store->holiday_mode;

        $this->shippingMode = $store->shipping_mode ?? 'flat';
        $this->flatFee = RinggitInput::fromSen($store->shipping_flat_fee_sen);
        $this->freeOver = RinggitInput::fromSen($store->free_shipping_over_sen);

        $savedMatrix = $store->shipping_matrix ?? [];
        foreach (MalaysianStates::ALL as $index => $stateName) {
            $fee = $savedMatrix[$stateName] ?? null;
            $this->matrix[$index] = RinggitInput::fromSen($fee === null ? null : (int) $fee);
        }

        $bank = $store->bank_details ?? [];
        $this->bankName = (string) ($bank['bank_name'] ?? '');
        $this->accountName = (string) ($bank['account_name'] ?? '');
        $this->accountNumber = (string) ($bank['account_number'] ?? '');
    }

    public function saveProfile(): void
    {
        $this->validate([
            'description' => ['required', 'string', 'max:2000'],
            'state' => ['required', Rule::in(MalaysianStates::ALL)],
            'logo' => ['nullable', 'image', 'max:2048'],
            'banner' => ['nullable', 'image', 'max:4096'],
        ]);

        $store = $this->currentStore();

        if ($this->logo !== null) {
            $store->addMedia($this->logo->getRealPath())
                ->usingFileName('logo-'.$this->logo->getClientOriginalName())
                ->toMediaCollection('logo');
        }

        if ($this->banner !== null) {
            $store->addMedia($this->banner->getRealPath())
                ->usingFileName('banner-'.$this->banner->getClientOriginalName())
                ->toMediaCollection('banner');
        }

        $store->update([
            'description' => $this->description,
            'state' => $this->state,
        ]);

        $this->reset('logo', 'banner');
        $this->dispatch('toast', message: __('Saved'));
    }

    /** Holiday mode persists immediately on toggle. */
    public function updatedHolidayMode(bool $value): void
    {
        $this->currentStore()->update(['holiday_mode' => $value]);

        $this->dispatch('toast', message: $value
            ? __('Holiday mode is on — buyers can\'t place orders.')
            : __('Holiday mode is off — you\'re open for orders.'));
    }

    /** Fill every state in the matrix with the "apply to all" amount. */
    public function applyToAll(): void
    {
        if (RinggitInput::toSen($this->applyAll) === null) {
            $this->addError('applyAll', __('Enter an amount in RM first, e.g. 8.00.'));

            return;
        }

        $this->resetErrorBag(array_merge(
            ['applyAll'],
            array_map(fn (int $index) => "matrix.$index", array_keys(MalaysianStates::ALL)),
        ));
        $this->matrix = array_fill(0, count(MalaysianStates::ALL), $this->applyAll);
    }

    public function saveShipping(): void
    {
        $this->validate([
            'shippingMode' => ['required', Rule::in(['flat', 'matrix'])],
        ]);

        $updates = ['shipping_mode' => $this->shippingMode];

        if ($this->shippingMode === 'flat') {
            $flatSen = RinggitInput::toSen($this->flatFee);

            if ($flatSen === null) {
                $this->addError('flatFee', __('Enter the shipping fee in RM, e.g. 8.00.'));

                return;
            }

            $updates['shipping_flat_fee_sen'] = $flatSen;
        } else {
            $matrixSen = [];

            foreach (MalaysianStates::ALL as $index => $stateName) {
                $fee = RinggitInput::toSen($this->matrix[$index] ?? null);

                if ($fee === null) {
                    $this->addError("matrix.$index", __('Enter a fee for :state.', ['state' => $stateName]));

                    continue;
                }

                $matrixSen[$stateName] = $fee;
            }

            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }

            $updates['shipping_matrix'] = $matrixSen;
        }

        $freeOverInput = trim($this->freeOver);

        if ($freeOverInput === '') {
            $updates['free_shipping_over_sen'] = null;
        } else {
            $freeOverSen = RinggitInput::toSen($freeOverInput);

            if ($freeOverSen === null) {
                $this->addError('freeOver', __('Enter the threshold in RM, e.g. 40.00 — or leave it empty.'));

                return;
            }

            $updates['free_shipping_over_sen'] = $freeOverSen;
        }

        $this->currentStore()->update($updates);

        $this->dispatch('toast', message: __('Saved'));
    }

    public function saveBank(): void
    {
        $this->validate([
            'bankName' => ['required', Rule::in(MalaysianBanks::ALL)],
            'accountName' => ['required', 'string', 'max:120'],
            'accountNumber' => ['required', 'regex:/^[0-9]{8,17}$/'],
        ], [
            'accountNumber.regex' => __('Enter the account number as 8–17 digits, no spaces or dashes.'),
        ]);

        $this->currentStore()->update([
            'bank_details' => [
                'bank_name' => $this->bankName,
                'account_name' => $this->accountName,
                'account_number' => $this->accountNumber,
            ],
        ]);

        $this->dispatch('toast', message: __('Saved'));
    }

    public function render()
    {
        return view('livewire.seller.settings', [
            'store' => $this->currentStore(),
            'states' => MalaysianStates::ALL,
            'banks' => MalaysianBanks::ALL,
        ])->title(__('Shop settings'));
    }
}
