<?php

use Illuminate\Support\Facades\Schedule;

// Scheduler — single source of truth, keep in sync with docs/10.
// M-11: withoutOverlapping because this makes a blocking 10s gateway requery
// PER ORDER while running every minute — a 7-order backlog outruns its own tick
// and stacks runs on the same rows. The queue worker below already does this.
Schedule::command('orders:expire-unpaid')->everyMinute()->withoutOverlapping(5);
Schedule::command('orders:auto-complete')->hourly();
Schedule::command('sitemap:generate')->dailyAt('03:00');
Schedule::command('returns:auto-escalate')->hourly();
Schedule::command('boosts:expire')->hourly();
// Monthly LHDN B2C e-invoice consolidation for the previous month (within the 7-day window).
Schedule::command('einvoice:consolidate')->monthlyOn(1, '04:00');
// M1.4 — abandoned-cart recovery + seller health scorecards.
Schedule::command('carts:remind-abandoned')->hourly();
Schedule::command('seller:compute-health')->dailyAt('02:00');
// M2.1 — expire lapsed Loyalty Coin lots.
Schedule::command('coins:expire')->dailyAt('01:00');
// Register any shipped parcel that missed its first AfterShip queue attempt.
// The command exits before querying when TRACKING_PROVIDER is not configured.
Schedule::command('tracking:register-open')->everyFifteenMinutes()->withoutOverlapping(5);

// Halal certificate expiry watch: delist products under an expired certificate,
// restore them on renewal, nudge sellers 60 days out. Early, before the day's
// trading — a product must not be on sale under a lapsed certificate for a
// working day because the job runs at noon.
Schedule::command('certificates:watch-expiry')->dailyAt('00:20');

// Affiliate commission comes off hold once the return window + buffer has
// passed. Hourly rather than daily so "unlocks 22 Aug" on the creator's
// dashboard is true within the hour rather than up to a day late.
Schedule::command('affiliates:lock-commissions')->hourly();
// M2.6 — close group-buy teams whose recruiting window lapsed.
Schedule::command('group-buy:expire')->everyFifteenMinutes();
// M2.8 — place orders for due subscribe-and-save schedules.
Schedule::command('subscriptions:process')->hourly();
// Ops — daily database + .env backup, then prune old ones (docs/10).
Schedule::command('backup:run')->dailyAt('02:00');
Schedule::command('backup:clean')->dailyAt('02:30');

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
