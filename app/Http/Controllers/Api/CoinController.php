<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\CheckoutException;
use App\Http\Api\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CoinTransaction;
use App\Services\CoinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Loyalty Coins (M2.1) as the web Coins page shows them. Coins are a count, not money. */
class CoinController extends Controller
{
    public function show(Request $request, CoinService $coins): JsonResponse
    {
        abort_unless($coins->enabled(), 404);

        $user = $request->user();
        $wallet = $coins->walletFor($user);

        $ledger = $wallet->transactions()->latest('created_at')->latest('id')->paginate(20);

        $expiringAt = $wallet->transactions()
            ->where('amount', '>', 0)
            ->where('remaining', '>', 0)
            ->whereNotNull('expires_at')
            ->orderBy('expires_at')
            ->value('expires_at');

        return ApiResponse::ok([
            'balance' => (int) $wallet->balance,
            'lifetime_earned' => (int) $wallet->lifetime_earned,
            'streak' => (int) $wallet->checkin_streak,
            'can_check_in' => $coins->canCheckInToday($user),
            'expiring_at' => $expiringAt === null ? null : Carbon::parse($expiringAt)->toIso8601String(),
            'ledger' => collect($ledger->items())->map(fn (CoinTransaction $txn) => [
                'id' => $txn->id,
                'type' => $txn->type->value,
                'type_label' => $txn->type->label(),
                'amount' => (int) $txn->amount,
                'note' => $txn->description,
                'created_at' => $txn->created_at?->toIso8601String(),
            ])->values()->all(),
        ], [
            'page' => $ledger->currentPage(),
            'per_page' => $ledger->perPage(),
            'total' => $ledger->total(),
            'last_page' => $ledger->lastPage(),
        ]);
    }

    public function checkIn(Request $request, CoinService $coins): JsonResponse
    {
        abort_unless($coins->enabled(), 404);

        try {
            return ApiResponse::ok($coins->checkIn($request->user()));
        } catch (CheckoutException $e) {
            return ApiResponse::error('already_checked_in', $e->getMessage(), 409);
        }
    }
}
