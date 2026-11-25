<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\AdminAlertNotification;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * HB-G206: ->onFailure() hook for the scheduled tasks that must not fail
 * silently. One mail to config('app.ops_alert_email'); with no address set,
 * the admin bell instead (the same AdminAlertNotification the other admin
 * alerts use), and the bell too when the mail cannot be sent. Never throws: an
 * alert that breaks the scheduler is worse. At most one alert per task per
 * Malaysia calendar day (G249); every failure is logged.
 */
class ScheduleFailureAlert
{
    public static function for(string $command): Closure
    {
        return function () use ($command): void {
            $message = "Scheduled task failed: {$command} (".config('app.name').')';
            Log::error($message);

            // G249: one alert per task per Malaysia day; an every-minute job failing
            // all day would otherwise send 1,440. The log above keeps every failure.
            $today = DisplayTime::now();
            try {
                $first = Cache::add("schedule-failure-alert:{$command}:{$today->toDateString()}", true, $today->copy()->endOfDay());
            } catch (\Throwable $e) {
                // Cache down: alert anyway; a duplicate mail beats a silent failure.
                $first = true;
                Log::error("Schedule failure alert cap for {$command} unavailable: ".$e->getMessage());
            }
            if (! $first) {
                return;
            }

            try {
                if ($to = config('app.ops_alert_email')) {
                    Mail::raw($message."\nCheck storage/logs and run it by hand.", fn ($m) => $m->to($to)->subject($message));

                    return;
                }
            } catch (\Throwable $e) {
                // Mail down (often the same outage that failed the task): fall through to the bell.
                Log::error("Schedule failure mail for {$command} not sent: ".$e->getMessage());
            }

            try {
                $admins = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->get();
                Notification::send($admins, new AdminAlertNotification($message));
            } catch (\Throwable $e) {
                Log::error("Schedule failure alert for {$command} not sent: ".$e->getMessage());
            }
        };
    }
}
