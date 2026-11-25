<?php

namespace App\Services;

use App\Enums\SubOrderStatus;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * PDPA "delete my account" (docs/09 §F) = anonymize + erase + soft delete,
 * shared by the web profile page and the mobile API. Orders, sub-orders and
 * their item snapshots are KEPT — they are legal/financial records (tax,
 * payouts, disputes). What is erased and what is kept is stated publicly on
 * /page/delete-account (PageSeeder) — change both together (HB-DEL2).
 */
class AccountDeletionService
{
    /**
     * Why this account cannot be deleted right now, or null when it can.
     */
    public function blocker(User $user): ?string
    {
        // HB-G237: the last superadmin is the only one who can grant access;
        // deleting them is the Staff screen's lockout by another door.
        // Locking read: run it through deleteUnlessBlocked() (HB-G234).
        if ($user->is_superadmin && User::lockedSuperadminCount() <= 1) {
            return __('You are the last superadmin — make another admin a superadmin before deleting your account.');
        }

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
     * blocker() and delete() in one transaction, so the superadmin count it
     * checked cannot change before the delete. Null when deleted, else why not.
     */
    public function deleteUnlessBlocked(User $user): ?string
    {
        return DB::transaction(function () use ($user) {
            $blocker = $this->blocker($user);

            if ($blocker === null) {
                $this->delete($user);
            }

            return $blocker;
        });
    }

    /**
     * Anonymize, erase what serves only this user, then soft delete: the row
     * stays so order history keeps its user_id, but every personal identifier
     * is gone. Mobile API tokens and push tokens go with it, whichever surface
     * asked. Callers go through deleteUnlessBlocked().
     */
    public function delete(User $user): void
    {
        $email = $user->email;

        $user->forceFill([
            'name' => 'Deleted user',
            'email' => 'deleted-'.$user->id.'@anonymized.local',
            'phone' => null,
            'phone_verified_at' => null,
            'google_id' => null,
            'two_factor_method' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'remember_token' => null,
        ])->save();

        $user->revokeAppAccess();
        $this->erasePersonalRows($user, $email);

        $user->delete();
    }

    /**
     * HB-DEL2: hard-delete the rows that serve only this user. A soft delete
     * never fires the user_id cascades, so without this the saved addresses
     * (name, phone, full address) outlived the account. Shared records stay:
     * orders + their shipping_address snapshot, reviews, questions, chats,
     * tickets, coins, affiliate and seller records (see /page/delete-account).
     */
    private function erasePersonalRows(User $user, string $email): void
    {
        // Subscriptions first: each points at a saved address.
        $user->subscriptions()->delete();
        $user->addresses()->delete();
        $user->wishlists()->delete();
        $user->cart()->delete(); // cart_items cascade
        $user->knownDevices()->delete();
        $user->otpCodes()->delete();
        $user->notifications()->delete();

        foreach (['product_views', 'stock_subscriptions', 'store_follows', 'sessions'] as $table) {
            DB::table($table)->where('user_id', $user->id)->delete();
        }

        DB::table('password_reset_tokens')->where('email', $email)->delete();
        DB::table('newsletter_subscribers')->where('user_id', $user->id)->orWhere('email', $email)->delete();

        // Search terms stay as anonymous totals; only the link to the person goes.
        DB::table('search_logs')->where('user_id', $user->id)->update(['user_id' => null]);

        // Helpful votes: helpful_count is derived from the pivot (AL-L3), so recount.
        $votedReviewIds = DB::table('review_helpfuls')->where('user_id', $user->id)->pluck('review_id');
        DB::table('review_helpfuls')->where('user_id', $user->id)->delete();
        Review::query()->whereKey($votedReviewIds)->update([
            'helpful_count' => DB::raw('(select count(*) from review_helpfuls where review_helpfuls.review_id = reviews.id)'),
        ]);
    }
}
