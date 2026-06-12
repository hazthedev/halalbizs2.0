<?php

namespace App\Livewire\Admin\Finance;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Payouts queue shell (docs/08 §F) — fully functional UI that activates
 * with M8 data: requested → approve → export bank CSV → mark paid (+ref),
 * or reject with a reason. Payout rows are plain status updates (no state
 * machine); the money side is the ledger's job:
 *
 * TODO(M8): LedgerService hooks —
 *   - approve: keep the requested entries earmarked against this payout.
 *   - reject: release the earmark back to the store's available balance.
 *   - mark paid: settle the earmark as a payout debit (negative amount_sen).
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

        // TODO(M8): LedgerService — entries earmarked for this payout stay
        // earmarked through approval; nothing to move yet.

        $this->dispatch('toast', message: __(':no approved — it joins the next bank CSV run.', ['no' => $payout->payout_no]));
    }

    public function openReject(int $payoutId): void
    {
        $this->rejectingId = $payoutId;
        $this->rejectReason = '';
        $this->resetValidation();
    }

    public function reject(): void
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

        // No dedicated rejection_reason column — `reference` is unused on the
        // rejected path, so it carries the reason (and the Payout activity log
        // records it, since `reference` is a logged attribute).
        $payout->update([
            'status' => PayoutStatus::Rejected,
            'reference' => trim($this->rejectReason),
            'processed_by' => auth()->id(),
        ]);

        // TODO(M8): LedgerService — release this payout's earmarked entries
        // back to the store's available balance.

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

        // TODO(M8): LedgerService — settle the earmarked entries as a payout
        // debit (negative amount_sen) linked to this payout.

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
