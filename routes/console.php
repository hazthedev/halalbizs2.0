<?php

use Illuminate\Support\Facades\Schedule;

// Scheduler — single source of truth, keep in sync with docs/10.
Schedule::command('orders:expire-unpaid')->everyMinute();
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
Schedule::command('queue:work database --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();
