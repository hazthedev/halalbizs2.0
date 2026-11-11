<?php

namespace App\Services;

use App\Enums\SubOrderStatus;
use App\Models\User;

/**
 * PDPA "delete my account" (docs/09 §F) = anonymize + soft delete, shared by
 * the web profile page and the mobile API. Orders, sub-orders and their item
 * snapshots are KEPT — they are legal/financial records (tax, payouts,
 * disputes); the retention stance is documented on the privacy page.
 */
class AccountDeletionService
{
    /**
     * Why this account cannot be deleted right now, or null when it can.
     */
    public function blocker(User $user): ?string
    {
        // Sellers must settle their ledger first — a non-zero balance is
        // either money we owe them or commission they owe us.
        if ($user->store !== null && ($user->store->availableBalanceSen() !== 0 || $user->store->pendingBalanceSen() !== 0)) {
            return __('Your store still has a ledger balance — settle it (payout or commission) before deleting your account.');
        }

        $openOrders = $user->orders()
            ->whereHas('subOrders', fn ($query) => $query->whereNotIn('status', [
                SubOrderStatus::Completed,
                SubOrderStatus::Cancelled,
                SubOrderStatus::Refunded,
            ]))
            ->exists();

        if ($openOrders) {
            return __('You still have orders in progress — wait for them to complete (or cancel them) first.');
        }

        return null;
    }

    /**
     * Anonymize, then soft delete: the row stays so order history keeps its
     * user_id, but every personal identifier is gone. Mobile API tokens and
     * push tokens go with it, whichever surface asked. Call blocker() first.
     */
    public function delete(User $user): void
    {
        $user->forceFill([
            'name' => 'Deleted user',
            'email' => 'deleted-'.$user->id.'@anonymized.local',
            'phone' => null,
        ])->save();

        $user->tokens()->delete();
        $user->deviceTokens()->delete();

        $user->delete();
    }
}
