<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * HB-DEL3: accounts deleted before HB-DEL2 (#282, live 2026-11-25) were only
 * anonymised and soft-deleted, so their addresses etc. are still there. This
 * runs the same erase step as a deletion today (AccountDeletionService::erase)
 * over every already-deleted account. Safe to rerun: a second run finds 0.
 *
 * Scope: soft-deleted AND email exactly deleted-<own id>@anonymized.local
 * (what AccountDeletionService writes). A live account can never match.
 * Prints counts only, never names or emails.
 */
class EraseDeletedAccounts extends Command
{
    protected $signature = 'accounts:erase-deleted {--dry-run : Count what would be erased, change nothing}';

    protected $description = 'Erase personal rows of accounts deleted before HB-DEL2 (run --dry-run first)';

    public function handle(AccountDeletionService $deletion): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $matched = 0;
        $skipped = 0;
        $totals = [];

        User::onlyTrashed()
            ->where('email', 'like', 'deleted-%@anonymized.local')
            ->chunkById(200, function ($users) use ($deletion, $dryRun, &$matched, &$skipped, &$totals) {
                foreach ($users as $user) {
                    if ($user->email !== 'deleted-'.$user->id.'@anonymized.local') {
                        $skipped++;

                        continue;
                    }

                    $matched++;
                    // The original email is gone, so rows keyed only by it are skipped (null).
                    $counts = $dryRun
                        ? $deletion->erase($user, null, dryRun: true)
                        : DB::transaction(fn () => $deletion->erase($user, null));

                    foreach ($counts as $table => $rows) {
                        $totals[$table] = ($totals[$table] ?? 0) + $rows;
                    }
                }
            });

        $this->info(($dryRun ? 'DRY RUN, nothing changed. ' : '')."Deleted accounts matched: {$matched}".($skipped ? ", skipped (email does not match its id): {$skipped}" : ''));
        $this->table(['Table', $dryRun ? 'Rows that would be erased' : 'Rows erased'], collect($totals)->map(fn ($rows, $table) => [$table, $rows])->values()->all());
        $this->line('Skipped by design: password_reset_tokens and guest newsletter rows keyed only by the original email (it is no longer stored; reset tokens expire on their own).');

        return self::SUCCESS;
    }
}
