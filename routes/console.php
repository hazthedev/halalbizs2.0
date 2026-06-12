<?php

use Illuminate\Support\Facades\Schedule;

// Scheduler — single source of truth, keep in sync with docs/10.
Schedule::command('orders:expire-unpaid')->everyMinute();
Schedule::command('orders:auto-complete')->hourly();
