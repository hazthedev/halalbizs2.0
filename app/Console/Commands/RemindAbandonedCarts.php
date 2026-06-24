<?php

namespace App\Console\Commands;

use App\Models\Cart;
use App\Notifications\AbandonedCartNotification;
use Illuminate\Console\Command;

/**
 * Abandoned-cart recovery (M1.4): nudge buyers whose cart has sat idle past a
 * threshold, at most once per cooldown window. Cart activity touches the cart
 * (CartItem::$touches), so updated_at tracks the last add/remove.
 */
class RemindAbandonedCarts extends Command
{
    protected $signature = 'carts:remind-abandoned {--idle-hours=4} {--cooldown-days=7}';

    protected $description = 'Email buyers about carts left idle past the threshold';

    public function handle(): int
    {
        $idleBefore = now()->subHours((int) $this->option('idle-hours'));
        $cooldownBefore = now()->subDays((int) $this->option('cooldown-days'));

        $carts = Cart::query()
            ->whereHas('items')
            ->where('updated_at', '<=', $idleBefore)
            ->where(fn ($query) => $query->whereNull('reminded_at')->orWhere('reminded_at', '<=', $cooldownBefore))
            ->with('user')
            ->get();

        $sent = 0;

        foreach ($carts as $cart) {
            if ($cart->user === null) {
                continue;
            }

            $cart->user->notify(new AbandonedCartNotification($cart->items()->count()));
            $cart->forceFill(['reminded_at' => now()])->save();
            $sent++;
        }

        $this->info("Abandoned-cart reminders sent: {$sent}");

        return self::SUCCESS;
    }
}
