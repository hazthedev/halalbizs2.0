<?php

namespace App\Livewire\Admin\Finance;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Services\LedgerService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Payouts queue (docs/08 §F + docs/09 §A): requested → approve → export
 * bank CSV → mark paid (+ref), or reject with a reason. The money side is
 * the ledger's: the payout request already wrote a NEGATIVE `payout` entry
 * (the earmark), so approve/mark-paid are pure status bookkeeping and only
 * reject touches the ledger — LedgerService::rejectPayout deletes the
 * earmark entry, restoring the store's available balance.
 */
#[Layout('layouts.admin')]
class Payouts extends Component
{
    use WithPagination;

    public const PER_PAGE = 20;

    #[Url(except: 'requested')]
    public string $tab = 'requested';

    /** @var list<string> selected payout ids (approved tab — bank CSV batch) */
    public array $selected = [];

    public bool $selectAll = false;

    public ?int $rejectingId = null;

    public string $rejectReason = '';

    public ?int $payingId = null;

    public string $paidReference = '';

    public function mount(): void
    {
        if (PayoutStatus::tryFrom($this->tab) === null) {
            $this->tab = PayoutStatus::Requested->value;
        }
    }

    public function updatedTab(string $value): void
    {
        if (PayoutStatus::tryFrom($value) === null) {
            $this->tab = PayoutStatus::Requested->value;
        }

        $this->reset('selected', 'selectAll', 'rejectingId', 'rejectReason', 'payingId', 'paidReference');
        $this->resetValidation();
        $this->resetPage();
    }

    /** Header checkbox — selects every approved payout (the export batch). */
    public function updatedSelectAll(bool $value): void
    {
        $this->selected = $value
            ? Payout::query()->where('status', PayoutStatus::Approved)->pluck('id')->map(fn (int $id) => (string) $id)->all()
            : [];
    }

    public function approve(int $payoutId): void
    {
        $payout = Payout::query()->findOrFail($payoutId);

        if ($payout->status !== PayoutStatus::Requested) {
            return;
        }

        $payout->update([
            'status' => PayoutStatus::Approved,
            'approved_at' => now(),
            'processed_by' => auth()->id(),
        ]);

        // Ledger: nothing to do — the negative payout entry written at
        // request time stays earmarked against this payout through approval.

        $this->dispatch('toast', message: __(':no approved — it joins the next bank CSV run.', ['no' => $payout->payout_no]));
    }

    public function openReject(int $payoutId): void
    {
        $this->rejectingId = $payoutId;
        $this->rejectReason = '';
        $this->resetValidation();
    }

    public function reject(LedgerService $ledger): void
    {
        $this->validate(
            ['rejectReason' => ['required', 'string', 'min:3', 'max:255']],
            [],
            ['rejectReason' => __('rejection reason')],
        );

        $payout = Payout::query()->findOrFail($this->rejectingId);

        if ($payout->status !== PayoutStatus::Requested) {
            return;
        }

        // The service flips the status, stores the reason in `reference`
        // (no dedicated rejection_reason column — it's unused on this path
        // and the Payout activity log records it), and DELETES the payout's
        // negative ledger entry — releasing the earmark back to the store's
        // available balance.
        $ledger->rejectPayout($payout, trim($this->rejectReason));

        $payout->update(['processed_by' => auth()->id()]);

        $this->reset('rejectingId', 'rejectReason');

        $this->dispatch('toast', message: __(':no rejected — funds return to the seller\'s available balance.', ['no' => $payout->payout_no]));
    }

    public function openMarkPaid(int $payoutId): void
    {
        $this->payingId = $payoutId;
        $this->paidReference = '';
        $this->resetValidation();
    }

    public function markPaid(): void
    {
        $this->validate(
            ['paidReference' => ['required', 'string', 'min:3', 'max:100']],
            [],
            ['paidReference' => __('bank reference')],
        );

        $payout = Payout::query()->findOrFail($this->payingId);

        if ($payout->status !== PayoutStatus::Approved) {
            return;
        }

        $payout->update([
            'status' => PayoutStatus::Paid,
            'paid_at' => now(),
            'reference' => trim($this->paidReference),
            'processed_by' => auth()->id(),
        ]);

        // Ledger: intentionally untouched. Per the accounting model
        // (docs/09 §A) the payout request already wrote the negative
        // `payout` entry, which is what reduced the available balance —
        // "paid" is purely status/paid_at/reference bookkeeping, so the
        // entry simply stays. Settling it again would double-debit.

        $this->selected = array_values(array_diff($this->selected, [(string) $payout->id]));

        $this->reset('payingId', 'paidReference');

        $this->dispatch('toast', message: __(':no marked paid.', ['no' => $payout->payout_no]));
    }

    /**
     * Bank CSV for the selected approved payouts — one file per run
     * (docs/08 §F): account number, account name, bank, amount in RM,
     * payout_no. Amount is built with integer math only (hard rule 1).
     */
    public function exportBankCsv()
    {
        $payouts = Payout::query()
            ->where('status', PayoutStatus::Approved)
            ->whereIn('id', array_map('intval', $this->selected))
            ->orderBy('payout_no')
            ->get();

        if ($payouts->isEmpty()) {
            $this->dispatch('toast', message: __('Select at least one approved payout to export.'), type: 'error');

            return;
        }

        $filename = 'payouts-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($payouts) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['account_number', 'account_name', 'bank', 'amount_rm', 'payout_no'], ',', '"', '');

            foreach ($payouts as $payout) {
                $bank = $payout->bank_snapshot ?? [];

                fputcsv($out, [
                    $bank['account_number'] ?? '',
                    $bank['account_name'] ?? '',
                    $bank['bank_name'] ?? '',
                    sprintf('%d.%02d', intdiv($payout->amount_sen, 100), $payout->amount_sen % 100),
                    $payout->payout_no,
                ], ',', '"', '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function render()
    {
        $payouts = Payout::query()
            ->with(['store', 'processedBy'])
            ->where('status', $this->tab)
            ->oldest('requested_at')
            ->oldest('id')
            ->paginate(self::PER_PAGE);

        return view('livewire.admin.finance.payouts', [
            'payouts' => $payouts,
            'counts' => $this->counts(),
        ])->title(__('Payouts'));
    }

    /** @return array<string, int> status value → count (one query) */
    private function counts(): array
    {
        $byStatus = Payout::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return collect(PayoutStatus::cases())
            ->mapWithKeys(fn (PayoutStatus $status) => [$status->value => (int) ($byStatus[$status->value] ?? 0)])
            ->all();
    }
}
