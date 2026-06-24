<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * docs/10 — prune database/.env backups older than the retention window.
 */
class CleanBackups extends Command
{
    protected $signature = 'backup:clean {--days= : Override the retention window}';

    protected $description = 'Delete backups older than the retention window';

    public function handle(): int
    {
        $disk = (string) config('backup.disk', 'local');
        $path = trim((string) config('backup.path', 'backups'), '/');
        $days = (int) ($this->option('days') ?? config('backup.retention_days', 14));
        $cutoff = now()->subDays(max(1, $days))->getTimestamp();

        $deleted = 0;

        foreach (Storage::disk($disk)->files($path) as $file) {
            if (Storage::disk($disk)->lastModified($file) < $cutoff) {
                Storage::disk($disk)->delete($file);
                $deleted++;
            }
        }

        $this->info("Removed {$deleted} backups older than {$days} days.");

        return self::SUCCESS;
    }
}
