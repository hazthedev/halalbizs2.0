<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

// HB-BAK3: a backup that produced no good file must exit non-zero, log, leave no file.
function fakeMysqlBackup(): void
{
    Storage::fake('local');
    config(['backup.disk' => 'local', 'backup.path' => 'backups']);
    $c = (string) config('database.default');
    config(["database.connections.{$c}.driver" => 'mysql', "database.connections.{$c}.password" => 'sup3r-s3cret']);
}

test('mysqldump failing exits non-zero, logs masked stderr, writes nothing', function () {
    fakeMysqlBackup();
    Process::fake(['mysqldump*' => Process::result('', "Access denied for user (using password: sup3r-s3cret) 1045", 2)]);
    Log::spy();

    $this->artisan('backup:run')->assertFailed();

    Log::shouldHaveReceived('error')->withArgs(fn ($m) => str_contains($m, '1045') && ! str_contains($m, 'sup3r-s3cret'))->once();
    expect(Storage::disk('local')->files('backups'))->toBeEmpty();
});

test('an empty or tiny dump with exit 0 counts as failure and is deleted', function (string $out) {
    fakeMysqlBackup();
    Process::fake(['mysqldump*' => Process::result($out)]);

    $this->artisan('backup:run')->assertFailed();

    expect(Storage::disk('local')->files('backups'))->toBeEmpty();
})->with(['empty' => [''], 'tiny' => ['-- dump']]);

test('mysqldump runs with --no-defaults as its first option', function () {
    fakeMysqlBackup();
    Process::fake(['mysqldump*' => Process::result(str_repeat('-- dump', 200))]);

    $this->artisan('backup:run')->assertSuccessful();

    Process::assertRan(fn ($p) => str_starts_with((string) $p->command, 'mysqldump --no-defaults '));
});
