<?php

namespace App\Services;

use App\Enums\StoreStatus;
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
        ])->save();

        $this->erase($user, $email);

        $user->delete();
    }

    /**
     * HB-DEL2: hard-delete the rows that serve only this user. A soft delete
     * never fires the user_id cascades, so without this the saved addresses
     * (name, phone, full address) outlived the account. Shared records stay:
     * orders + their shipping_address snapshot, reviews, questions, chats,
     * tickets, coins, affiliate and seller records (see /page/delete-account).
     * HB-DEL3: a seller's shop closes with the account.
     *
     * Shared by delete() and accounts:erase-deleted (accounts deleted before
     * HB-DEL2). $email = the original address; null when it is already gone,
     * so rows keyed only by it cannot be matched. $dryRun counts, writes
     * nothing. Returns rows per table erased (or that would be).
     *
     * @return array<string, int>
     */
    public function erase(User $user, ?string $email, bool $dryRun = false): array
    {
        $counts = [];
        $run = function (string $table, $query, ?array $update = null) use (&$counts, $dryRun): void {
            $counts[$table] = $dryRun ? $query->count() : ($update === null ? $query->delete() : $query->update($update));
        };

        $columns = ['phone_verified_at', 'google_id', 'two_factor_method', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'];
        $run('users (sign-in columns)', User::withTrashed()->whereKey($user->id)
            ->where(fn ($query) => collect($columns)->each(fn ($column) => $query->orWhereNotNull($column))),
            array_fill_keys($columns, null));

        // Subscriptions first: each points at a saved address.
        $run('subscriptions', $user->subscriptions());
        $run('addresses', $user->addresses());
        $run('wishlists', $user->wishlists());
        $run('carts', $user->cart()); // cart_items cascade
        $run('known_devices', $user->knownDevices());
        $run('otp_codes', $user->otpCodes());
        $run('notifications', $user->notifications());
        $run('personal_access_tokens', $user->tokens());
        $run('device_tokens', $user->deviceTokens());

        foreach (['product_views', 'stock_subscriptions', 'store_follows', 'sessions'] as $table) {
            $run($table, DB::table($table)->where('user_id', $user->id));
        }

        if ($email !== null) {
            $run('password_reset_tokens', DB::table('password_reset_tokens')->where('email', $email));
        }

        $run('newsletter_subscribers', DB::table('newsletter_subscribers')->where(
            fn ($query) => $query->where('user_id', $user->id)->when($email !== null, fn ($query) => $query->orWhere('email', $email))
        ));

        // Search terms stay as anonymous totals; only the link to the person goes.
        $run('search_logs (unlinked)', DB::table('search_logs')->where('user_id', $user->id), ['user_id' => null]);

        // Helpful votes: helpful_count is derived from the pivot (AL-L3), so recount.
        $votedReviewIds = DB::table('review_helpfuls')->where('user_id', $user->id)->pluck('review_id');
        $run('review_helpfuls', DB::table('review_helpfuls')->where('user_id', $user->id));
        if (! $dryRun) {
            Review::query()->whereKey($votedReviewIds)->update([
                'helpful_count' => DB::raw('(select count(*) from review_helpfuls where review_helpfuls.review_id = reviews.id)'),
            ]);
        }

        // HB-DEL3: the shop closes with the account: the admin "Suspend" state,
        // so Product::live() and every approved() read path drop it (storefront,
        // search, sitemap, API). Store, products, documents, ledger and
        // certificates stay as records. An admin suspension keeps its own reason.
        $store = $user->store()->first();
        $counts['stores (closed)'] = (int) in_array($store?->status, [StoreStatus::Approved, StoreStatus::Pending], true);
        if ($counts['stores (closed)'] === 1 && ! $dryRun) {
            $store->forceFill(['status' => StoreStatus::Suspended, 'rejection_reason' => 'Owner account deleted.'])->save();
        }

        return $counts;
    }
}
