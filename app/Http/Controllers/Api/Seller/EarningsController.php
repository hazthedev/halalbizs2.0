<?php

namespace App\Http\Controllers\Api\Seller;

use App\Enums\PayoutStatus;
use App\Exceptions\CheckoutException;
use App\Http\Api\ApiResponse;
use App\Models\Payout;
use App\Models\StoreLedgerEntry;
use App\Services\LedgerService;
use App\Settings\OrderSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The store's escrow ledger and payout requests (docs/09 §A, mobile plan
 * §4). Balance maths lives in LedgerService; this only reads and relays.
 * `meta` paginates the ledger entries.
 */
class EarningsController extends SellerController
{
    public const PER_PAGE = 20;

    public function index(Request $request, LedgerService $ledger, OrderSettings $settings): JsonResponse
    {
        $store = $this->currentStore($request);

        $entries = $store->ledgerEntries()->latest('created_at')->latest('id')->paginate(self::PER_PAGE);

        $open = $store->payouts()
            ->whereIn('status', [PayoutStatus::Requested, PayoutStatus::Approved])
            ->latest('requested_at')
            ->first();

        return ApiResponse::ok([
            'available_sen' => $ledger->availableBalanceSen($store),
            'held_sen' => $store->heldBalanceSen(),
            'paid_out_sen' => (int) $store->payouts()->where('status', PayoutStatus::Paid)->sum('amount_sen'),
            'payout_min_sen' => $settings->payout_min_sen,
            'open_payout' => $open === null ? null : $this->payout($open),
            'ledger' => collect($entries->items())->map(fn (StoreLedgerEntry $entry) => [
                'id' => $entry->id,
                'type' => $entry->type->value,
                'type_label' => $entry->type->label(),
                'amount_sen' => $entry->amount_sen,
                'reference' => $entry->description,
                'sub_order_id' => $entry->sub_order_id,
                'created_at' => $entry->created_at?->toIso8601String(),
            ])->values(),
            'payouts' => $store->payouts()->latest('requested_at')->latest('id')->take(20)->get()
                ->map(fn (Payout $payout) => $this->payout($payout))->values(),
        ], [
            'page' => $entries->currentPage(),
            'per_page' => $entries->perPage(),
            'total' => $entries->total(),
            'last_page' => $entries->lastPage(),
        ]);
    }

    public function requestPayout(Request $request, LedgerService $ledger): JsonResponse
    {
        $data = $request->validate(['amount_sen' => ['required', 'integer', 'min:1']]);

        try {
            // The service guards everything: minimum, available balance
            // (negative blocks), and one open request at a time.
            $payout = $ledger->requestPayout($this->currentStore($request), $data['amount_sen']);
        } catch (CheckoutException $e) {
            return ApiResponse::error('payout_rejected', $e->getMessage(), 409);
        }

        return ApiResponse::ok($this->payout($payout), null, 201);
    }

    /** @return array<string, mixed> */
    private function payout(Payout $payout): array
    {
        return [
            'id' => $payout->id,
            'payout_no' => $payout->payout_no,
            'amount_sen' => $payout->amount_sen,
            'status' => $payout->status->value,
            'status_label' => $payout->status->label(),
            'requested_at' => $payout->requested_at?->toIso8601String(),
            'paid_at' => $payout->paid_at?->toIso8601String(),
        ];
    }
}
