<?php

namespace App\Livewire\Storefront\Account;

use App\Enums\SubOrderStatus;
use App\Enums\TwoFactorMethod;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SubOrder;
use App\Services\OtpService;
use App\Settings\GeneralSettings;
use App\Support\Totp;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    // ── Security: two-factor ────────────────────────────────────────────

    public bool $emailSetupPending = false;

    public string $email_setup_code = '';

    public ?string $totpSecret = null;

    public string $totp_setup_code = '';

    /** @var array<int, string>|null Shown once after enabling/regenerating. */
    public ?array $freshRecoveryCodes = null;

    public string $disable_password = '';

    // ── Security: phone verification ────────────────────────────────────

    public string $verify_phone = '';

    public bool $phoneOtpPending = false;

    public string $phone_otp_code = '';

    // ── PDPA danger zone ────────────────────────────────────────────────

    public bool $showDeleteModal = false;

    public string $delete_confirm = '';

    public string $delete_password = '';

    public function mount(): void
    {
        $user = auth()->user();

        $this->name = $user->name;
        $this->phone = $user->phone;
        $this->verify_phone = $user->phone ?? '';
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

        $user = auth()->user();

        // A different number is a different phone — verification resets.
        if ($data['phone'] !== $user->phone) {
            $data['phone_verified_at'] = null;
            $this->verify_phone = $data['phone'] ?? '';
            $this->phoneOtpPending = false;
        }

        $user->update($data);

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

    // ── Two-factor authentication ───────────────────────────────────────

    public function startEmailTwoFactor(): void
    {
        $user = auth()->user();

        if ($user->hasTwoFactor()) {
            return;
        }

        if (! app(OtpService::class)->issue($user, OtpService::PURPOSE_2FA_EMAIL)) {
            $this->emailSetupPending = true; // the earlier code is still valid
            $this->addError('email_setup_code', __('A code was sent moments ago — check your inbox or wait a minute to request another.'));

            return;
        }

        $this->emailSetupPending = true;
        $this->totpSecret = null;
        $this->dispatch('toast', message: __('Code sent — check your email.'));
    }

    public function confirmEmailTwoFactor(): void
    {
        $user = auth()->user();

        if ($user->hasTwoFactor()) {
            return;
        }

        $this->validate(['email_setup_code' => ['required', 'string']]);

        $otp = app(OtpService::class);

        if (! $otp->verify($user, OtpService::PURPOSE_2FA_EMAIL, trim($this->email_setup_code))) {
            $this->addError('email_setup_code', $otp->hasActiveCode($user, OtpService::PURPOSE_2FA_EMAIL)
                ? __('That code isn\'t right — check the latest email and try again.')
                : __('That code no longer works — request a new code and enter it within 10 minutes.'));

            return;
        }

        $user->forceFill(['two_factor_method' => TwoFactorMethod::Email])->save();

        $this->reset('emailSetupPending', 'email_setup_code');
        $this->dispatch('toast', message: __('Two-factor authentication enabled'));
    }

    public function startTotpSetup(): void
    {
        if (auth()->user()->hasTwoFactor()) {
            return;
        }

        $this->totpSecret = app(Totp::class)->generateSecret();
        $this->reset('emailSetupPending', 'email_setup_code', 'totp_setup_code');
    }

    public function confirmTotpSetup(): void
    {
        $user = auth()->user();

        if ($user->hasTwoFactor() || $this->totpSecret === null) {
            return;
        }

        $this->validate(['totp_setup_code' => ['required', 'string']]);

        if (! app(Totp::class)->verify($this->totpSecret, trim($this->totp_setup_code))) {
            $this->addError('totp_setup_code', __('That code isn\'t right — make sure the secret is saved in your app, then enter the current code.'));

            return;
        }

        $codes = $this->newRecoveryCodes();

        $user->forceFill([
            'two_factor_method' => TwoFactorMethod::Totp,
            'two_factor_secret' => $this->totpSecret,
            'two_factor_recovery_codes' => $codes,
        ])->save();

        $this->freshRecoveryCodes = $codes;
        $this->reset('totpSecret', 'totp_setup_code');
        $this->dispatch('toast', message: __('Two-factor authentication enabled'));
    }

    public function cancelTwoFactorSetup(): void
    {
        $this->reset('emailSetupPending', 'email_setup_code', 'totpSecret', 'totp_setup_code');
        $this->resetErrorBag(['email_setup_code', 'totp_setup_code']);
    }

    public function regenerateRecoveryCodes(): void
    {
        $user = auth()->user();

        if ($user->two_factor_method !== TwoFactorMethod::Totp) {
            return;
        }

        $codes = $this->newRecoveryCodes();

        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();

        $this->freshRecoveryCodes = $codes;
        $this->dispatch('toast', message: __('New recovery codes generated — the old ones no longer work.'));
    }

    public function dismissRecoveryCodes(): void
    {
        $this->freshRecoveryCodes = null;
    }

    public function disableTwoFactor(): void
    {
        $this->validate([
            'disable_password' => ['required', 'current_password'],
        ], [
            'disable_password.current_password' => __('That doesn\'t match your current password — try again.'),
        ]);

        auth()->user()->forceFill([
            'two_factor_method' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        $this->reset('disable_password', 'freshRecoveryCodes');
        $this->dispatch('toast', message: __('Two-factor authentication disabled'));
    }

    /**
     * @return array<int, string>
     */
    private function newRecoveryCodes(): array
    {
        return collect(range(1, 10))
            ->map(fn () => strtoupper(Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    // ── Phone verification ──────────────────────────────────────────────

    public function sendPhoneCode(): void
    {
        $user = auth()->user();

        $digits = preg_replace('/[\s\-]/', '', trim($this->verify_phone)) ?? '';

        // Malaysian mobile: 01X-XXXXXXX(X), optionally +60-prefixed.
        if (! preg_match('/^(\+?60|0)1\d{8,9}$/', $digits)) {
            $this->addError('verify_phone', __('Enter a Malaysian mobile number — e.g. 012-345 6789.'));

            return;
        }

        if (trim($this->verify_phone) !== $user->phone) {
            $user->forceFill([
                'phone' => trim($this->verify_phone),
                'phone_verified_at' => null,
            ])->save();

            $this->phone = $user->phone;
        }

        if (! app(OtpService::class)->issue($user, OtpService::PURPOSE_PHONE_VERIFY)) {
            $this->phoneOtpPending = true; // the earlier code is still valid
            $this->addError('phone_otp_code', __('A code was sent moments ago — wait a minute before requesting another.'));

            return;
        }

        $this->phoneOtpPending = true;
        $this->resetErrorBag(['verify_phone', 'phone_otp_code']);
        $this->dispatch('toast', message: __('Code sent by SMS.'));
    }

    public function confirmPhoneCode(): void
    {
        $user = auth()->user();

        $this->validate(['phone_otp_code' => ['required', 'string']]);

        $otp = app(OtpService::class);

        if (! $otp->verify($user, OtpService::PURPOSE_PHONE_VERIFY, trim($this->phone_otp_code))) {
            $this->addError('phone_otp_code', $otp->hasActiveCode($user, OtpService::PURPOSE_PHONE_VERIFY)
                ? __('That code isn\'t right — check the SMS and try again.')
                : __('That code no longer works — request a new code and enter it within 10 minutes.'));

            return;
        }

        $user->forceFill(['phone_verified_at' => now()])->save();

        $this->reset('phoneOtpPending', 'phone_otp_code');
        $this->dispatch('toast', message: __('Phone number verified'));
    }

    /**
     * PDPA "export my data" (docs/09 §F). The docs target a queued zip with
     * a signed 7-day download link for production volume; a synchronous JSON
     * stream is the right size for the data we hold per user today.
     */
    public function downloadData(): StreamedResponse
    {
        $user = auth()->user();

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'preferred_locale' => $user->preferred_locale,
                'preferred_currency' => $user->preferred_currency,
                'registered_at' => $user->created_at?->toIso8601String(),
            ],
            'addresses' => $user->addresses()->get()->map(fn (Address $address) => [
                'label' => $address->label,
                'recipient_name' => $address->recipient_name,
                'phone' => $address->phone,
                'line1' => $address->line1,
                'line2' => $address->line2,
                'postcode' => $address->postcode,
                'city' => $address->city,
                'state' => $address->state,
                'country' => $address->country,
                'is_default' => $address->is_default,
            ])->all(),
            'orders' => $user->orders()->with('subOrders.items')->latest('placed_at')->get()->map(fn (Order $order) => [
                'order_no' => $order->order_no,
                'payment_method' => $order->payment_method->value,
                'payment_status' => $order->payment_status->value,
                'shipping_address' => $order->shipping_address,
                'subtotal_sen' => $order->subtotal_sen,
                'shipping_total_sen' => $order->shipping_total_sen,
                'discount_total_sen' => $order->discount_total_sen,
                'grand_total_sen' => $order->grand_total_sen,
                'placed_at' => $order->placed_at?->toIso8601String(),
                'sub_orders' => $order->subOrders->map(fn (SubOrder $subOrder) => [
                    'sub_order_no' => $subOrder->sub_order_no,
                    'status' => $subOrder->status->value,
                    'items_subtotal_sen' => $subOrder->items_subtotal_sen,
                    'shipping_fee_sen' => $subOrder->shipping_fee_sen,
                    'total_sen' => $subOrder->total_sen,
                    // order_items are purchase-time snapshots (hard rule 5)
                    'items' => $subOrder->items->map(fn (OrderItem $item) => [
                        'product_name' => $item->product_name,
                        'variant_label' => $item->variant_label,
                        'unit_price_sen' => $item->unit_price_sen,
                        'qty' => $item->qty,
                        'line_total_sen' => $item->line_total_sen,
                    ])->all(),
                ])->all(),
            ])->all(),
        ];

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }, 'my-data-'.now()->format('Ymd-His').'.json', ['Content-Type' => 'application/json']);
    }

    /**
     * PDPA "delete my account" (docs/09 §F) = anonymize + soft delete.
     * Orders, sub-orders and their item snapshots are KEPT — they are
     * legal/financial records (tax, payouts, disputes); the retention
     * stance is documented on the privacy page.
     */
    public function deleteAccount()
    {
        $this->validate([
            'delete_confirm' => ['required', 'in:DELETE'],
            'delete_password' => ['required', 'current_password'],
        ], [
            'delete_confirm.in' => __('Type DELETE in capitals to confirm.'),
            'delete_password.current_password' => __('That doesn\'t match your current password — try again.'),
        ]);

        $user = auth()->user();

        // Sellers must settle their ledger first — a non-zero balance is
        // either money we owe them or commission they owe us.
        if ($user->store !== null && $user->store->availableBalanceSen() !== 0) {
            $this->dispatch('toast', message: __('Your store still has a ledger balance — settle it (payout or commission) before deleting your account.'), type: 'error');

            return;
        }

        $openOrders = $user->orders()
            ->whereHas('subOrders', fn ($query) => $query->whereNotIn('status', [
                SubOrderStatus::Completed,
                SubOrderStatus::Cancelled,
                SubOrderStatus::Refunded,
            ]))
            ->exists();

        if ($openOrders) {
            $this->dispatch('toast', message: __('You still have orders in progress — wait for them to complete (or cancel them) first.'), type: 'error');

            return;
        }

        // Anonymize, then soft delete: the row stays so order history keeps
        // its user_id, but every personal identifier is gone.
        $user->forceFill([
            'name' => 'Deleted user',
            'email' => 'deleted-'.$user->id.'@anonymized.local',
            'phone' => null,
        ])->save();

        $user->delete();

        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        session()->flash('toast', ['message' => __('Your account has been deleted. Take care.'), 'type' => 'success']);

        return redirect()->route('home');
    }

    public function render()
    {
        return view('livewire.storefront.account.profile', [
            'locales' => app(GeneralSettings::class)->enabled_locales,
            'currencies' => app(GeneralSettings::class)->display_currencies,
            'otpauthUri' => $this->totpSecret !== null
                ? app(Totp::class)->otpauthUri($this->totpSecret, auth()->user()->email)
                : null,
        ])->title(__('Profile'));
    }
}
