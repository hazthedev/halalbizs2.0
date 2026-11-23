<?php

namespace App\Livewire\Admin\Finance;

use App\Enums\PayoutStatus;
use App\Livewire\Concerns\WithTable;
use App\Models\Payout;
use App\Services\LedgerService;
use App\Support\DisplayTime;
use App\Support\JsonSearch;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Payouts queue (docs/08 §F + docs/09 §A): requested → approve → export
 * bank CSV → mark paid (+ref), or reject with a reason. The money side is
 * the ledger's: the payout request already wrote a NEGATIVE `payout` entry
 * (the earmark), so approve/mark-paid are pure status bookkeeping and only
 * reject touches the ledger — LedgerService::rejectPayout books a reversal
 * entry against the earmark (never deletes it), restoring the store's
 * available balance.
 */
#[Layout('layouts.admin')]
class Payouts extends Component
{
    use WithTable;

    public const PER_PAGE = 20;

    protected function tableFilters(): array
    {
        return ['tab'];
    }

    protected function tableSorts(): array
    {
        return ['payout' => 'payout_no', 'amount' => 'amount_sen', 'requested' => 'requested_at'];
    }

    protected function tableDefaultOrder(): array
    {
        return ['requested_at' => 'asc'];
    }

    #[Url(except: 'requested')]
    public string $tab = 'requested';

    /** @var list<string> selected payout ids (approved tab — bank CSV batch) */
    public array $selected = [];

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

        $this->reset('selected', 'rejectingId', 'rejectReason', 'payingId', 'paidReference');
        $this->resetValidation();
        $this->resetPage();
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
        // and the Payout activity log records it), and books a positive
        // `payout_reversal` entry against the payout's negative one —
        // releasing the earmark back to the store's available balance.
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
     *
     * `selected` is client input (HB-G168): each id is re-checked here —
     * a non-id ("12x", an array) is dropped, never cast, and the query keeps
     * only payouts still approved. Bad ids are skipped; valid ones export.
     * Read-only (no status or ledger write), so no lock is needed.
     */
    public function exportBankCsv()
    {
        $ids = array_filter($this->selected, fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id)));

        $payouts = Payout::query()
            ->where('status', PayoutStatus::Approved)
            ->whereIn('id', $ids)
            ->orderBy('payout_no')
            ->get();

        if ($payouts->isEmpty()) {
            $this->dispatch('toast', message: __('Select at least one approved payout to export.'), type: 'error');

            return;
        }

        $filename = 'payouts-'.DisplayTime::now()->format('Ymd-His').'.csv';

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
        return view('livewire.admin.finance.payouts', [
            'payouts' => $this->results(),
            'counts' => $this->counts(),
        ])->title(__('Payouts'));
    }

    private function results()
    {
        $term = JsonSearch::pattern($this->search);

        return $this->sortTable(Payout::query()
            ->with(['store', 'processedBy'])
            ->where('status', $this->tab)
            ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($q) => $q
                ->whereRaw(JsonSearch::clause('payout_no'), [$term])
                ->orWhereHas('store', fn ($s) => $s->whereRaw(JsonSearch::clause('name'), [$term])))))
            ->paginate($this->tablePageSize());
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
