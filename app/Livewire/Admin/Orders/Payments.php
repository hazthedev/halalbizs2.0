<?php

namespace App\Livewire\Admin\Orders;

use App\Enums\GatewayPaymentStatus;
use App\Enums\PaymentMethod;
use App\Jobs\ConfirmIpay88PaymentJob;
use App\Models\Payment;
use App\Services\Ipay88Service;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
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

    #[Url(except: '')]
    public string $status = '';

    /** "Mismatches only" — rows whose response signature failed verification. */
    #[Url(except: false)]
    public bool $mismatchesOnly = false;

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'mismatchesOnly'], true)) {
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

    public function render()
    {
        $payments = Payment::query()
            ->with('order')
            ->when(GatewayPaymentStatus::tryFrom($this->status), fn ($query, $status) => $query->where('status', $status))
            ->when($this->mismatchesOnly, fn ($query) => $query->where('signature_valid', false))
            ->latest('created_at')
            ->latest('id')
            ->paginate(self::PER_PAGE);

        return view('livewire.admin.orders.payments', [
            'payments' => $payments,
            'statuses' => GatewayPaymentStatus::cases(),
        ])->title(__('Payments'));
    }
}
