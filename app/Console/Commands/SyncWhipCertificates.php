<?php

namespace App\Console\Commands;

use App\Enums\CertificateStatus;
use App\Models\HalalCertificate;
use App\Models\HalalCertificateEvent;
use App\Services\Whip\WhipDirectory;
use App\Support\DisplayTime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keeps WHIP-sourced certificates in step with WHIP (read-only).
 *
 * WHIP is asked about EVERY source=whip row first; only if all answers came
 * back does anything change, so a dead connection changes nothing. Dates and
 * status then follow WHIP. Expired is a date state, so the existing expiry
 * watch is run afterwards to delist exactly as it does daily. Idempotent.
 */
class SyncWhipCertificates extends Command
{
    protected $signature = 'certificates:sync-whip';

    protected $description = 'Re-check WHIP-sourced halal certificates against WHIP and follow its status and dates';

    public function handle(WhipDirectory $whip): int
    {
        $certificates = HalalCertificate::query()->where('source', 'whip')->with('store')->get();

        try {
            $answers = $certificates->map(function (HalalCertificate $c) use ($whip) {
                $found = $whip->lookup($c->number);

                return [$c, $found ? $found->answerFor($c->store?->registration_no) : 'not_found', $found];
            });
        } catch (Throwable $e) {
            Log::error('certificates:sync-whip: WHIP unreachable, nothing changed', ['error' => $e->getMessage()]);
            $this->error('WHIP unreachable; nothing changed.');

            return self::FAILURE;
        }

        $changed = 0;

        foreach ($answers as [$cert, $answer, $found]) {
            $before = $cert->whip_answer;

            if ($found !== null && in_array($answer, ['verified', 'expired'], true)) {
                $cert->takeFromWhip($found, $answer);
                // verified: WHIP vouches again (also restores a row an earlier sync rejected).
                $answer === 'verified' && $cert->forceFill(['status' => CertificateStatus::Approved, 'review_note' => null]);
            } else {
                // Gone, not certified, or no longer this store's: WHIP no longer vouches.
                $cert->forceFill([
                    'status' => CertificateStatus::Rejected,
                    'review_note' => __('WHIP no longer lists this certificate as certified for your company.'),
                    'whip_synced_at' => now(),
                    'whip_answer' => $answer,
                ]);
            }

            $cert->save();

            // WHIP's product list may have changed: re-apply it to what is bound or waiting.
            $found !== null && $cert->bindProducts(
                [...$cert->products()->pluck('id'), ...($cert->whip_unmatched_product_ids ?? [])], $found);

            if ($before !== $answer) {
                $changed++;
                HalalCertificateEvent::create([
                    'halal_certificate_id' => $cert->id,
                    'occurred_on' => DisplayTime::today()->toDateString(),
                    'summary' => __('WHIP check: :answer', ['answer' => str_replace('_', ' ', $answer)]),
                ]);
            }
        }

        // Same effects as the daily watch: lapsed terms come off sale.
        $this->call('certificates:watch-expiry');

        $this->info("WHIP sync: {$certificates->count()} checked, {$changed} changed.");

        return self::SUCCESS;
    }
}
