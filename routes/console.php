<?php

use Illuminate\Support\Facades\Schedule;

// Scheduler — single source of truth, keep in sync with docs/10.
Schedule::command('orders:expire-unpaid')->everyMinute();
Schedule::command('orders:auto-complete')->hourly();
Schedule::command('sitemap:generate')->dailyAt('03:00');
Schedule::command('returns:auto-escalate')->hourly();
