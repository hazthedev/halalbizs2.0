<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * docs/10 — daily database + .env backup to the configured disk (R2/S3 in prod).
 * Supports the SQLite (file copy) and MySQL (mysqldump) connections this app
 * runs on. The .env snapshot is sensitive — keep the backup disk private.
 */
class RunBackup extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Back up the database (+ .env) to the backup disk';

    public function handle(): int
    {
        $disk = (string) config('backup.disk', 'local');
        $path = trim((string) config('backup.path', 'backups'), '/');
        $stamp = now()->format('Y-m-d_His');

        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");

        $stored = match ($driver) {
            'sqlite' => $this->backupSqlite($connection, $disk, "{$path}/db-{$stamp}.sqlite"),
            'mysql' => $this->backupMysql($connection, $disk, "{$path}/db-{$stamp}.sql"),
            default => null,
        };

        if ($stored === null) {
            $this->error("Unsupported database driver for backup: {$driver}");

            return self::FAILURE;
        }

        if ($stored === false) {
            return self::FAILURE;
        }

        if (is_file(base_path('.env'))) {
            // The .env snapshot is plaintext secrets — DB password, APP_KEY, SMTP
            // password, DEPLOY_TOKEN. On a LOCAL disk it is written to storage/ on
            // the very host it is meant to protect: it survives nothing the host
            // does not, so it buys no recovery, and on shared hosting it sits
            // beside other tenants. Warning about it was not enough — 14 rolling
            // copies accumulated on the preview. Only a remote disk gets it;
            // pointing BACKUP_DISK at docs/10's private bucket restores it with
            // no other change.
            if (config("filesystems.disks.{$disk}.driver") === 'local') {
                $this->warn("BACKUP_DISK is [{$disk}] — skipping the .env snapshot. Point it at a private bucket to include it.");
            } else {
                Storage::disk($disk)->put("{$path}/env-{$stamp}.txt", (string) file_get_contents(base_path('.env')));
            }
        }

        $this->info("Backup written to [{$disk}] {$path} ({$stamp}).");

        return self::SUCCESS;
    }

    private function backupSqlite(string $connection, string $disk, string $target): bool
    {
        $file = (string) config("database.connections.{$connection}.database");

        if ($file === ':memory:' || ! is_file($file)) {
            $this->error('SQLite database file not found.');

            return false;
        }

        Storage::disk($disk)->put($target, (string) file_get_contents($file));

        return true;
    }

    private function backupMysql(string $connection, string $disk, string $target): bool
    {
        $cfg = (array) config("database.connections.{$connection}");

        // MYSQL_PWD, not --password=: the command line of every process is
        // world-readable via `ps` on a shared host.
        $result = Process::env(['MYSQL_PWD' => (string) ($cfg['password'] ?? '')])->run(sprintf(
            'mysqldump --host=%s --port=%s --user=%s --single-transaction --quick %s',
            escapeshellarg((string) ($cfg['host'] ?? '127.0.0.1')),
            escapeshellarg((string) ($cfg['port'] ?? '3306')),
            escapeshellarg((string) ($cfg['username'] ?? '')),
            escapeshellarg((string) ($cfg['database'] ?? '')),
        ));

        if (! $result->successful()) {
            $this->error('mysqldump failed: '.$result->errorOutput());

            return false;
        }

        Storage::disk($disk)->put($target, $result->output());

        return true;
    }
}
