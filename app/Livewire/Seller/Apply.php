<?php

namespace App\Livewire\Seller;

use App\Enums\DocumentStatus;
use App\Enums\StoreStatus;
use App\Models\Store;
use App\Models\StoreDocument;
use App\Notifications\SellerApplicationReceived;
use App\Services\Turnstile;
use App\Support\MalaysianBanks;
use App\Support\MalaysianStates;
use App\Support\ReservedSubdomains;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Seller application (docs/07 §A1) — storefront-side, any logged-in buyer.
 * Submits a pending store + SSM/IC documents; admin approval happens in M7.
 */
#[Layout('layouts.storefront')]
class Apply extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $description = '';

    public string $state = '';

    public bool $sstRegistered = false;

    public string $sstNumber = '';

    public string $bankName = '';

    public string $accountName = '';

    public string $accountNumber = '';

    public ?TemporaryUploadedFile $ssmFile = null;

    public ?TemporaryUploadedFile $icFile = null;

    public bool $confirm = false;

    public ?string $turnstileToken = null;

    public function mount(): void
    {
        $store = auth()->user()->store;

        if ($store !== null) {
            $this->redirectRoute($store->isApproved() ? 'seller.dashboard' : 'seller.status', navigate: true);
        }
    }

    #[Computed]
    public function slugPreview(): string
    {
        return Str::slug($this->name);
    }

    #[Computed]
    public function slugTaken(): bool
    {
        if ($this->slugPreview === '') {
            return false;
        }

        // withTrashed: soft-deleted stores still hold the unique slug index.
        return Store::withTrashed()->where('slug', $this->slugPreview)->exists();
    }

    public function submit(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:60', function (string $attribute, mixed $value, \Closure $fail) {
                if (Store::withTrashed()->where('slug', Str::slug((string) $value))->exists()) {
                    $fail(__('This shop name is already taken — try another.'));
                }

                if (ReservedSubdomains::isReserved(Str::slug((string) $value))) {
                    $fail(__('This shop name is reserved — try another.'));
                }
            }],
            'description' => ['required', 'string', 'max:2000'],
            'state' => ['required', Rule::in(MalaysianStates::ALL)],
            'sstRegistered' => ['boolean'],
            'sstNumber' => ['required_if:sstRegistered,true', 'nullable', 'string', 'max:30'],
            'bankName' => ['required', Rule::in(MalaysianBanks::ALL)],
            'accountName' => ['required', 'string', 'max:120'],
            'accountNumber' => ['required', 'regex:/^[0-9]{8,17}$/'],
            'ssmFile' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'icFile' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'confirm' => ['accepted'],
        ], [
            'accountNumber.regex' => __('Enter the account number as 8–17 digits, no spaces or dashes.'),
            'sstNumber.required_if' => __('Enter your SST registration number.'),
            'confirm.accepted' => __('Please confirm the information you provided is accurate.'),
        ]);

        if (! app(Turnstile::class)->verify($this->turnstileToken, request()->ip())) {
            $this->addError('turnstileToken', __('We couldn\'t verify you\'re human — refresh the page and try again.'));

            return;
        }

        $user = auth()->user();

        DB::transaction(function () use ($user) {
            $store = Store::create([
                'user_id' => $user->id,
                'name' => $this->name,
                'description' => $this->description,
                'status' => StoreStatus::Pending,
                'state' => $this->state,
                'sst_registered' => $this->sstRegistered,
                'sst_number' => $this->sstRegistered ? $this->sstNumber : null,
                'bank_details' => [
                    'bank_name' => $this->bankName,
                    'account_name' => $this->accountName,
                    'account_number' => $this->accountNumber,
                ],
            ]);

            foreach (['ssm' => $this->ssmFile, 'ic' => $this->icFile] as $type => $file) {
                $document = StoreDocument::create([
                    'store_id' => $store->id,
                    'type' => $type,
                    'status' => DocumentStatus::Pending,
                ]);

                $document->addMedia($file->getRealPath())
                    ->usingFileName($type.'-'.$file->getClientOriginalName())
                    ->toMediaCollection('file');
            }

            $user->notify(new SellerApplicationReceived($store));
        });

        $this->redirectRoute('seller.status', navigate: true);
    }

    public function render()
    {
        return view('livewire.seller.apply', [
            'states' => MalaysianStates::ALL,
            'banks' => MalaysianBanks::ALL,
        ])->title(__('Become a seller'));
    }
}
