<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * docs/10 — daily database + .env backup to the configured disk (R2/S3 in prod).
 * Supports the SQLite (file copy) and MySQL (mysqldump) connections this app
 * runs on. The .env snapshot is sensitive — keep the backup disk private.
 */
class RunBackup extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Back up the database (+ .env) to the backup disk';

    /** A real dump is always bigger; a smaller file means the dump broke (HB-BAK3). */
    private const MIN_BYTES = 1024;

    public function handle(): int
    {
        try {
            return $this->backup();
        } catch (Throwable $e) {
            // HB-BAK3: any failure is logged (with secrets masked) and exits non-zero,
            // so the schedule's onFailure alert fires.
            $msg = $this->mask($e->getMessage());
            Log::error('backup:run failed: '.$msg);
            $this->error('Backup failed: '.$msg);

            return self::FAILURE;
        }
    }

    private function backup(): int
    {
        $disk = (string) config('backup.disk', 'local');
        $path = trim((string) config('backup.path', 'backups'), '/');
        $stamp = now()->format('Y-m-d_His');

        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");

        $target = match ($driver) {
            'sqlite' => $this->backupSqlite($connection, $disk, "{$path}/db-{$stamp}.sqlite"),
            'mysql' => $this->backupMysql($connection, $disk, "{$path}/db-{$stamp}.sql"),
            default => null,
        };

        if ($target === null) {
            Log::error("backup:run: unsupported database driver: {$driver}");
            $this->error("Unsupported database driver for backup: {$driver}");

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

    private function backupSqlite(string $connection, string $disk, string $target): string
    {
        $file = (string) config("database.connections.{$connection}.database");

        if ($file === ':memory:' || ! is_file($file)) {
            throw new RuntimeException('SQLite database file not found.');
        }

        $this->write($disk, $target, (string) file_get_contents($file));

        return $target;
    }

    private function backupMysql(string $connection, string $disk, string $target): string
    {
        $cfg = (array) config("database.connections.{$connection}");

        // --no-defaults FIRST: ~/.my.cnf [client] otherwise beats MYSQL_PWD (live 1045, 2026-11-26).
        // MYSQL_PWD, not --password=: the command line of every process is
        // world-readable via `ps` on a shared host.
        $result = Process::env(['MYSQL_PWD' => (string) ($cfg['password'] ?? '')])->run(sprintf(
            'mysqldump --no-defaults --host=%s --port=%s --user=%s --single-transaction --quick %s',
            escapeshellarg((string) ($cfg['host'] ?? '127.0.0.1')),
            escapeshellarg((string) ($cfg['port'] ?? '3306')),
            escapeshellarg((string) ($cfg['username'] ?? '')),
            escapeshellarg((string) ($cfg['database'] ?? '')),
        ));

        if (! $result->successful()) {
            throw new RuntimeException('mysqldump failed (exit '.$result->exitCode().'): '.trim($result->errorOutput()));
        }

        $this->write($disk, $target, $result->output());

        return $target;
    }

    /** G246: a local dump is owner-only (0600) from the moment it exists. */
    private function write(string $disk, string $target, string $contents): void
    {
        $storage = Storage::disk($disk);

        // put() returns false (not throws) on a failed write; a tiny file is a broken dump.
        if (! $storage->put($target, $contents) || $storage->size($target) < self::MIN_BYTES) {
            $storage->delete($target);

            throw new RuntimeException("backup file missing or under ".self::MIN_BYTES." bytes: {$target}");
        }

        if (config("filesystems.disks.{$disk}.driver") === 'local') {
            chmod(Storage::disk($disk)->path($target), 0600);
        }
    }

    /** Strip the DB password and anything shaped like one from text before it is logged. */
    private function mask(string $text): string
    {
        $pwd = (string) config('database.connections.'.config('database.default').'.password');
        if ($pwd !== '') {
            $text = str_replace($pwd, '***', $text);
        }

        return (string) preg_replace('/(pass(?:word)?\s*[=:]\s*)\S+/i', '$1***', $text);
    }
}
