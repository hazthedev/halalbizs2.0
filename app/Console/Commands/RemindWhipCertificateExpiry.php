<?php

namespace App\Console\Commands;

use App\Models\HalalCertificate;
use App\Notifications\WhipCertificateExpiry;
use App\Support\DisplayTime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemindWhipCertificateExpiry extends Command
{
    protected $signature = 'certificates:remind-whip-expiry';

    protected $description = 'Remind WHIP sellers 60, 30 and 7 days before certificate expiry';

    public function handle(): int
    {
        if (! filter_var(config('services.whip.renew_url'), FILTER_VALIDATE_URL)) {
            $this->error('Configure the WHIP renewal URL before sending reminders.');

            return self::FAILURE;
        }
        $count = 0;
        foreach ([60 => 30, 30 => 7, 7 => 0] as $days => $next) {
            HalalCertificate::query()->where('source', 'whip')->approved()
                ->where('whip_answer', 'verified')->where('whip_synced_at', '>=', now()->subHours(24))
                ->whereDate('valid_from', '<=', DisplayTime::today())
                ->whereDate('valid_to', '<=', DisplayTime::today()->addDays($days))
                ->whereDate('valid_to', '>', DisplayTime::today()->addDays($next))
                ->chunkById(100, function ($certificates) use ($days, $next, &$count) {
                    foreach ($certificates as $certificate) {
                        $count += DB::transaction(function () use ($certificate, $days, $next) {
                            $certificate = HalalCertificate::lockForUpdate()->findOrFail($certificate->id);
                            if ($certificate->source !== 'whip' || ! $certificate->isApproved()
                                || $certificate->whip_answer !== 'verified' || $certificate->isWhipStale()
                                || ! $certificate->isValid() || $certificate->daysRemaining() > $days || $certificate->daysRemaining() <= $next) {
                                return 0;
                            }
                            $store = $certificate->store;
                            if ($store?->registration_verified_at === null || $store->user === null) {
                                return 0;
                            }
                            $expiry = $certificate->valid_to->toDateString();
                            $sent = ($certificate->whip_reminders['expiry'] ?? null) === $expiry
                                ? ($certificate->whip_reminders['sent'] ?? []) : [];
                            if (in_array($days, $sent, true)) {
                                return 0;
                            }
                            // Database queue inserts share this transaction: a failed enqueue rolls back the reminder stamp.
                            $store->user->notify((new WhipCertificateExpiry($certificate, $certificate->daysRemaining()))->beforeCommit());
                            $certificate->forceFill(['whip_reminders' => ['expiry' => $expiry, 'sent' => [...$sent, $days]]])->save();

                            return 1;
                        });
                    }
                });
        }
        $this->info("WHIP expiry reminders: {$count} sent.");

        return self::SUCCESS;
    }
}
