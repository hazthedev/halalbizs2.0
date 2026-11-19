<?php

namespace App\Livewire\Admin\Orders;

use App\Enums\GatewayPaymentStatus;
use App\Enums\PaymentMethod;
use App\Jobs\ConfirmIpay88PaymentJob;
use App\Models\Payment;
use App\Services\Ipay88Service;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;
use Throwable;

/**
 * Payments reconciliation (docs/08 §E) — payments vs iPay88 status, a
 * per-row requery button, and signature_valid mismatches highlighted.
 * Fulfilment only ever happens through ConfirmIpay88PaymentJob, which
 * re-runs the requery itself (hard rule 4).
 */
#[Layout('layouts.admin')]
class Payments extends Component
{
    use WithPagination;

    public const PER_PAGE = 20;

    /** Audit-log description of a manual portal settle (HB-G122). */
    public const SETTLED_IN_PORTAL = 'payment refund settled in portal';

    #[Url(except: '')]
    public string $status = '';

    /** "Mismatches only" — rows whose response signature failed verification. */
    #[Url(except: false)]
    public bool $mismatchesOnly = false;

    /**
     * "Needs portal refund" — the gateway did not perform a refund we have
     * already recorded, so a human owes one in the merchant portal. This is the
     * actionable half of the reconciliation job, the same way mismatchesOnly is.
     */
    #[Url(except: false)]
    public bool $needsPortalRefund = false;

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'mismatchesOnly', 'needsPortalRefund'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Manual requery for stuck iPay88 rows. "00" means paid at the gateway —
     * the confirm job is run synchronously (it requeries again itself and
     * fulfils the order); anything else is surfaced raw for the admin.
     */
    public function requery(int $paymentId): void
    {
        $payment = Payment::query()->findOrFail($paymentId);

        if ($payment->gateway !== PaymentMethod::Ipay88
            || ! in_array($payment->status, [GatewayPaymentStatus::Pending, GatewayPaymentStatus::Failed], true)) {
            return;
        }

        try {
            $result = app(Ipay88Service::class)->requery($payment->ref_no, $payment->amount_sen);
        } catch (Throwable $e) {
            report($e);
            $this->dispatch('toast', message: __('Requery failed — could not reach iPay88. Try again shortly.'), type: 'error');

            return;
        }

        if ($result === '00') {
            ConfirmIpay88PaymentJob::dispatchSync($payment);

            $this->dispatch('toast', message: __('Confirmed — :ref is paid and its sub-orders moved to confirmed.', ['ref' => $payment->ref_no]));

            return;
        }

        // Keep the raw gateway answer on the row for the reconciliation grid.
        $payment->update(['requery_result' => $result]);

        $this->dispatch('toast', message: __('iPay88 answered: :result', ['result' => $result]), type: 'error');
    }

    /**
     * HB-G122: a human finished a refund the gateway did not perform (the
     * "Needs portal refund" flag RefundService leaves). Clears the flag and
     * keeps who, which payment and the portal reference in the audit log.
     * The guard repeats the route's EnsureAdmin + can:orders.manage because
     * this moves a money flag.
     */
    public function markRefundSettled(int $paymentId, ?string $reference = null): void
    {
        $user = auth()->user();
        abort_unless($user?->hasRole('admin') && $user->can('orders.manage'), 403);

        $reference = trim((string) $reference);

        if (mb_strlen($reference) > 100) {
            $this->dispatch('toast', message: __('The portal reference is too long (100 characters max).'), type: 'error');

            return;
        }

        $payment = Payment::query()->findOrFail($paymentId);

        // Conditional write: only a flagged row flips, so a double click or a
        // stale page cannot write a second audit row. One transaction, so the
        // flag never clears without its audit row.
        $flipped = DB::transaction(function () use ($payment, $user, $reference) {
            $flipped = Payment::query()->whereKey($payment->id)
                ->where('gateway_refund_ok', false)
                ->update(['gateway_refund_ok' => true]);

            if ($flipped === 0) {
                return 0;
            }

            activity()
                ->causedBy($user)
                ->performedOn($payment)
                ->withProperties([
                    'payment_id' => $payment->id,
                    'reference' => $reference === '' ? null : $reference,
                    // The audit-log screen renders old/attributes as its Diff.
                    'old' => ['gateway_refund_ok' => false],
                    'attributes' => ['gateway_refund_ok' => true],
                ])
                ->log(self::SETTLED_IN_PORTAL);

            return $flipped;
        });

        if ($flipped === 0) {
            return;
        }

        $this->dispatch('toast', message: __('Refund for :ref marked settled in the iPay88 portal.', ['ref' => $payment->ref_no]));
    }

    public function render()
    {
        $payments = Payment::query()
            ->with('order')
            ->when(GatewayPaymentStatus::tryFrom($this->status), fn ($query, $status) => $query->where('status', $status))
            ->when($this->mismatchesOnly, fn ($query) => $query->where('signature_valid', false))
            ->when($this->needsPortalRefund, fn ($query) => $query->where('gateway_refund_ok', false))
            ->latest('created_at')
            ->latest('id')
            ->paginate(self::PER_PAGE);

        // The flag has no "how" column: true means the gateway refunded OR a
        // human settled it in the portal. The audit log tells them apart, so
        // a manual settle never reads as "Gateway confirmed".
        $clearedIds = $payments->getCollection()->where('gateway_refund_ok', true)->modelKeys();
        $settledInPortal = $clearedIds === [] ? [] : Activity::query()
            ->where('description', self::SETTLED_IN_PORTAL)
            ->where('subject_type', (new Payment)->getMorphClass())
            ->whereIn('subject_id', $clearedIds)
            ->pluck('subject_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return view('livewire.admin.orders.payments', [
            'payments' => $payments,
            'settledInPortal' => $settledInPortal,
            'statuses' => GatewayPaymentStatus::cases(),
        ])->title(__('Payments'));
    }
}
