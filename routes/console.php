<?php

use App\Support\ScheduleFailureAlert;
use Illuminate\Support\Facades\Schedule;

// Scheduler — single source of truth, keep in sync with docs/10.
// M-11: withoutOverlapping because this makes a blocking 10s gateway requery
// PER ORDER while running every minute — a 7-order backlog outruns its own tick
// and stacks runs on the same rows. The queue worker below already does this.
Schedule::command('orders:expire-unpaid')->everyMinute()->withoutOverlapping(5)->onFailure(ScheduleFailureAlert::for('orders:expire-unpaid'));
Schedule::command('orders:auto-complete')->hourly()->onFailure(ScheduleFailureAlert::for('orders:auto-complete'));
// PAY-14 (HB-DEC-1124d): day 5 seller warning, day 7 not shipped → cancelled.
Schedule::command('orders:cancel-unshipped')->hourly()->withoutOverlapping(30)->onFailure(ScheduleFailureAlert::for('orders:cancel-unshipped'));
Schedule::command('sitemap:generate')->dailyAt('03:00');
Schedule::command('returns:auto-escalate')->hourly()->onFailure(ScheduleFailureAlert::for('returns:auto-escalate'));
Schedule::command('boosts:expire')->hourly();
// Monthly LHDN B2C e-invoice consolidation for the previous month (within the 7-day window).
Schedule::command('einvoice:consolidate')->monthlyOn(1, '04:00')->onFailure(ScheduleFailureAlert::for('einvoice:consolidate'));
// M1.4 — abandoned-cart recovery + seller health scorecards.
Schedule::command('carts:remind-abandoned')->hourly();
Schedule::command('seller:compute-health')->dailyAt('02:00');
// M2.1 — expire lapsed Loyalty Coin lots.
Schedule::command('coins:expire')->dailyAt('01:00')->onFailure(ScheduleFailureAlert::for('coins:expire'));
// Register any shipped parcel that missed its first AfterShip queue attempt.
// The command exits before querying when TRACKING_PROVIDER is not configured.
Schedule::command('tracking:register-open')->everyFifteenMinutes()->withoutOverlapping(5);

// Halal certificate expiry watch: delist products under an expired certificate,
// restore them on renewal, nudge manual-certificate sellers 90 days out. Early, before the day's
// trading — a product must not be on sale under a lapsed certificate for a
// working day because the job runs at noon.
Schedule::command('certificates:watch-expiry')->dailyAt('00:20')->onFailure(ScheduleFailureAlert::for('certificates:watch-expiry'));
// Follow WHIP (read-only) for certificates it vouches for. Every 4 h, so the
// 24 h freshness rule on the badge has five tries before it drops.
Schedule::command('certificates:sync-whip')->everyFourHours()->withoutOverlapping(30);
Schedule::command('certificates:remind-whip-expiry')->dailyAt('08:00')->timezone('Asia/Kuala_Lumpur')->withoutOverlapping(30);

// Affiliate commission comes off hold once the return window + buffer has
// passed. Hourly rather than daily so "unlocks 22 Aug" on the creator's
// dashboard is true within the hour rather than up to a day late.
Schedule::command('affiliates:lock-commissions')->hourly()->onFailure(ScheduleFailureAlert::for('affiliates:lock-commissions'));
// Seller money comes off hold once the return window closes.
Schedule::command('ledger:release-holds')->hourly()->onFailure(ScheduleFailureAlert::for('ledger:release-holds'));
// COD commission debt: monthly statement to stores with a negative balance.
Schedule::command('ledger:send-statements')->monthlyOn(1, '09:00');
// M2.6 — close group-buy teams whose recruiting window lapsed.
Schedule::command('group-buy:expire')->everyFifteenMinutes();
// M2.8 — place orders for due subscribe-and-save schedules.
Schedule::command('subscriptions:process')->hourly()->onFailure(ScheduleFailureAlert::for('subscriptions:process'));
// Ops — daily database + .env backup, then prune old ones (docs/10).
Schedule::command('backup:run')->dailyAt('02:00')->appendOutputTo(storage_path('logs/schedule-backup.log'))->onFailure(ScheduleFailureAlert::for('backup:run'));
Schedule::command('backup:clean')->dailyAt('02:30');
// Mobile API — expired bearer tokens (config/sanctum.php `expiration`) are
// already refused; this just deletes the rows a day after they lapse.
Schedule::command('sanctum:prune-expired --hours=24')->dailyAt('03:00');

// Async queue drain — shared cPanel hosting has no supervisor / persistent worker,
// so ride the scheduler cron: a short-lived worker each minute that processes the
// database queue and exits. Pinned to the `database` connection so it's a harmless
// no-op while QUEUE_CONNECTION=sync and starts working the instant that flips.
// ponytail: no second cron, no daemon — the one schedule:run cron covers everything.
//
// ⚠ --queue IS NOT OPTIONAL. Without it a worker takes `default` and ONLY
// `default`, so every job that names its own queue sits unclaimed forever —
// which is what happened: ConfirmIpay88PaymentJob is on `payments`, so paid
// orders were never fulfilled. Highest-value first (Laravel drains this list in
// order); `search` is last because it is bulk and the slowest.
//
// Adding a queue name anywhere in app/ means adding it HERE too. The test
// tests/Feature/Ops/QueueWorkerCoversEveryQueueTest.php fails if you don't.
Schedule::command('queue:work database --queue=payments,einvoice,webhooks,coins,affiliate,default,search --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();
