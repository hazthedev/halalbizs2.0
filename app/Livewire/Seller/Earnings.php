<?php

namespace App\Livewire\Seller;

use App\Enums\PayoutStatus;
use App\Exceptions\CheckoutException;
use App\Livewire\Concerns\CurrentStore;
use App\Services\LedgerService;
use App\Settings\OrderSettings;
use App\Support\Csv;
use App\Support\JsonSearch;
use App\Support\RinggitInput;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Seller earnings (docs/09 §A): the store's escrow ledger, the available /
 * pending / paid-out balance cards, and the payout request form. All money
 * is integer sen; the balance maths lives in LedgerService — this component
 * only reads and relays.
 */
#[Layout('layouts.seller')]
class Earnings extends Component
{
    use CurrentStore, WithPagination;

    public const PER_PAGE = 20;

    /** RM amount typed by the seller — parsed via RinggitInput, never floats. */
    public string $amount = '';

    public int $perPage = self::PER_PAGE;

    /** Per-table sort state; the key is table-prefixed ("ledger:date") and whitelisted below. */
    public string $ledgerSearch = '';

    public string $payoutSearch = '';

    public string $ledgerSort = '';

    public string $ledgerDir = 'asc';

    public string $payoutSort = '';

    public string $payoutDir = 'asc';

    private const LEDGER_SORTS = ['ledger:date' => 'created_at', 'ledger:type' => 'type', 'ledger:amount' => 'amount_sen'];

    private const PAYOUT_SORTS = ['payout:payout' => 'payout_no', 'payout:amount' => 'amount_sen', 'payout:status' => 'status', 'payout:requested' => 'requested_at', 'payout:paid' => 'paid_at'];

    public function sortBy(string $field): void
    {
        if (array_key_exists($field, self::LEDGER_SORTS)) {
            $this->ledgerDir = $this->ledgerSort === $field && $this->ledgerDir === 'asc' ? 'desc' : 'asc';
            $this->ledgerSort = $field;
            $this->resetPage('ledgerPage');
        } elseif (array_key_exists($field, self::PAYOUT_SORTS)) {
            $this->payoutDir = $this->payoutSort === $field && $this->payoutDir === 'asc' ? 'desc' : 'asc';
            $this->payoutSort = $field;
            $this->resetPage('payoutPage');
        }
    }

    public function updatedLedgerSearch(): void
    {
        $this->resetPage('ledgerPage');
    }

    public function updatedPayoutSearch(): void
    {
        $this->resetPage('payoutPage');
    }

    public function updatedPerPage(): void
    {
        $this->resetPage('ledgerPage');
        $this->resetPage('payoutPage');
    }

    public function requestPayout(LedgerService $ledger): void
    {
        $amountSen = RinggitInput::toSen($this->amount);

        if ($amountSen === null || $amountSen <= 0) {
            $this->addError('amount', __('Enter an amount in RM — e.g. 120.00.'));

            return;
        }

        $this->resetErrorBag('amount');

        try {
            // The service guards everything: minimum, available balance
            // (negative blocks), and one open request at a time.
            $payout = $ledger->requestPayout($this->currentStore(), $amountSen);
        } catch (CheckoutException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');

            return;
        }

        $this->reset('amount');
        $this->resetPage('ledgerPage');
        $this->resetPage('payoutPage');

        $this->dispatch('toast', message: __('Payout :no requested — we\'ll review it and run the bank transfer.', ['no' => $payout->payout_no]));
    }

    /** Download the full ledger as a CSV (M1.5). */
    public function exportCsv(): StreamedResponse
    {
        $store = $this->currentStore();

        $rows = $store->ledgerEntries()
            ->latest('created_at')->latest('id')->get()
            ->map(fn ($entry) => [
                $entry->created_at?->display()->toDateTimeString(),
                $entry->type->value,
                $entry->status->value,
                RinggitInput::fromSen($entry->amount_sen),
                $entry->description,
            ]);

        return Csv::stream("earnings-{$store->slug}.csv", ['datetime', 'type', 'status', 'amount_rm', 'description'], $rows);
    }

    public function render()
    {
        $store = $this->currentStore();
        $ledger = app(LedgerService::class);

        return view('livewire.seller.earnings', [
            'availableSen' => $ledger->availableBalanceSen($store),
            'onHoldSen' => $ledger->pendingBalanceSen($store), // completed, inside the return window
            'heldSen' => $store->heldBalanceSen(), // escrow — released on order completion (M1.4)
            'pendingPayout' => $store->payouts()
                ->whereIn('status', [PayoutStatus::Requested, PayoutStatus::Approved])
                ->latest('requested_at')
                ->first(),
            'paidOutSen' => (int) $store->payouts()->where('status', PayoutStatus::Paid)->sum('amount_sen'),
            'entries' => $this->sorted($store->ledgerEntries()->when(trim($this->ledgerSearch) !== '', fn ($q) => $q->whereRaw(JsonSearch::clause('description'), [JsonSearch::pattern($this->ledgerSearch)])), self::LEDGER_SORTS, $this->ledgerSort, $this->ledgerDir, 'created_at')->paginate($this->pageSize(), pageName: 'ledgerPage'),
            'payouts' => $this->sorted($store->payouts()->when(trim($this->payoutSearch) !== '', fn ($q) => $q->where(fn ($w) => $w->whereRaw(JsonSearch::clause('payout_no'), [JsonSearch::pattern($this->payoutSearch)])->orWhereRaw(JsonSearch::clause('reference'), [JsonSearch::pattern($this->payoutSearch)])->orWhereRaw(JsonSearch::clause('status'), [JsonSearch::pattern($this->payoutSearch)]))), self::PAYOUT_SORTS, $this->payoutSort, $this->payoutDir, 'requested_at')->paginate($this->pageSize(), pageName: 'payoutPage'),
            'minSen' => app(OrderSettings::class)->payout_min_sen,
            'bank' => $store->bank_details ?? [],
        ])->title(__('Earnings'));
    }

    private function pageSize(): int
    {
        return in_array($this->perPage, [15, 20, 50, 100], true) ? $this->perPage : self::PER_PAGE;
    }

    /** Whitelisted order only: the client sends a key, never a column name. */
    private function sorted($query, array $map, string $sort, string $dir, string $default)
    {
        $column = $map[$sort] ?? null;

        return $column !== null
            ? $query->orderBy($column, $dir === 'desc' ? 'desc' : 'asc')->orderByDesc('id')
            : $query->latest($default)->latest('id');
    }
}
