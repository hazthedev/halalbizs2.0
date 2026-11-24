<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\AdminAlertNotification;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * HB-G206: ->onFailure() hook for the scheduled tasks that must not fail
 * silently. One mail to config('app.ops_alert_email'); with no address set,
 * the admin bell instead (the same AdminAlertNotification the other admin
 * alerts use). Never throws: an alert that breaks the scheduler is worse.
 */
class ScheduleFailureAlert
{
    public static function for(string $command): Closure
    {
        return function () use ($command): void {
            $message = "Scheduled task failed: {$command} (".config('app.name').')';
            Log::error($message);

            try {
                if ($to = config('app.ops_alert_email')) {
                    Mail::raw($message."\nCheck storage/logs and run it by hand.", fn ($m) => $m->to($to)->subject($message));

                    return;
                }

                $admins = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->get();
                Notification::send($admins, new AdminAlertNotification($message));
            } catch (\Throwable $e) {
                Log::error("Schedule failure alert for {$command} not sent: ".$e->getMessage());
            }
        };
    }
}
