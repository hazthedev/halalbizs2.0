<?php

namespace App\Livewire\Storefront\Account;

use App\Enums\SubOrderStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SubOrder;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Auth;
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

    // ── PDPA danger zone ────────────────────────────────────────────────

    public bool $showDeleteModal = false;

    public string $delete_confirm = '';

    public string $delete_password = '';

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
        ])->title(__('Profile'));
    }
}
