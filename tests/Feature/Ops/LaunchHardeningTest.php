<?php

use App\Http\Middleware\SecurityHeaders;
use App\Models\Product;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

test('the /up health route responds ok (DB reachable)', function () {
    $this->get('/up')->assertOk();
});

test('the public API applies a rate limit', function () {
    $this->getJson('/api/v1/categories')
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', '60');
});

test('SecurityHeaders adds HSTS on a secure request only', function () {
    $middleware = new SecurityHeaders;
    $next = fn () => new Response('ok');

    $secure = $middleware->handle(Request::create('https://halalbizs.test/', 'GET'), $next);
    $plain = $middleware->handle(Request::create('http://halalbizs.test/', 'GET'), $next);

    expect($secure->headers->get('Strict-Transport-Security'))->not->toBeNull()
        ->and($plain->headers->get('Strict-Transport-Security'))->toBeNull();
});

test('backup:clean removes only backups older than the retention window', function () {
    Storage::fake('local');
    config(['backup.disk' => 'local', 'backup.path' => 'backups', 'backup.retention_days' => 14]);

    Storage::disk('local')->put('backups/db-old.sqlite', 'old');
    Storage::disk('local')->put('backups/db-new.sqlite', 'new');
    touch(Storage::disk('local')->path('backups/db-old.sqlite'), now()->subDays(30)->getTimestamp());

    $this->artisan('backup:clean')->assertSuccessful();

    Storage::disk('local')->assertMissing('backups/db-old.sqlite');
    Storage::disk('local')->assertExists('backups/db-new.sqlite');
});

test('backup:run writes a database + env snapshot', function () {
    Storage::fake('local');
    config(['backup.disk' => 'local', 'backup.path' => 'backups']);

    // Never repoint database.default: RefreshDatabase rolls back in teardown and
    // would open a fresh connection to whatever this now names. Set up the
    // branch belonging to the driver the suite is actually running on instead.
    $connection = (string) config('database.default');
    $tmp = null;

    if (config("database.connections.{$connection}.driver") === 'sqlite') {
        // Only the config the command reads is touched — the already-open
        // connection keeps its own handle.
        $tmp = tempnam(sys_get_temp_dir(), 'dbbk').'.sqlite';
        file_put_contents($tmp, 'sqlite-bytes');
        config(["database.connections.{$connection}.database" => $tmp]);
    } else {
        Process::fake(['mysqldump*' => Process::result('-- dump')]);
    }

    $this->artisan('backup:run')->assertSuccessful();

    $files = collect(Storage::disk('local')->files('backups'));
    expect($files->filter(fn ($f) => str_contains($f, 'db-'))->count())->toBe(1)
        ->and($files->filter(fn ($f) => str_contains($f, 'env-'))->count())->toBe(1);

    if ($tmp !== null) {
        @unlink($tmp);
    }
});

/**
 * The mysqldump branch is the only one production uses, so it gets a test on
 * both drivers: forcing the connection's driver config (not database.default)
 * leaves the open connection — and RefreshDatabase's rollback — untouched.
 */
test('backup:run dumps mysql without putting the password on the command line', function () {
    Storage::fake('local');
    config(['backup.disk' => 'local', 'backup.path' => 'backups']);
    Process::fake(['mysqldump*' => Process::result('-- dump')]);

    $connection = (string) config('database.default');
    config([
        "database.connections.{$connection}.driver" => 'mysql',
        "database.connections.{$connection}.host" => 'db.internal',
        "database.connections.{$connection}.port" => '3306',
        "database.connections.{$connection}.username" => 'hbiz',
        "database.connections.{$connection}.password" => 'sup3r-s3cret',
        "database.connections.{$connection}.database" => 'hbiz_prod',
    ]);

    $this->artisan('backup:run')->assertSuccessful();

    Process::assertRan(function (PendingProcess $process) {
        return str_contains((string) $process->command, 'mysqldump')
            && str_contains((string) $process->command, "'hbiz_prod'")
            && ! str_contains((string) $process->command, 'sup3r-s3cret')
            && ($process->environment['MYSQL_PWD'] ?? null) === 'sup3r-s3cret';
    });

    expect(Storage::disk('local')->files('backups'))
        ->toHaveCount(2); // the dump + the .env snapshot
});

test('backup:run warns in production when the .env snapshot lands on a local disk', function () {
    Storage::fake('local');
    config(['backup.disk' => 'local', 'backup.path' => 'backups']);
    Process::fake(['mysqldump*' => Process::result('-- dump')]);

    $connection = (string) config('database.default');
    config(["database.connections.{$connection}.driver" => 'mysql']);
    $this->app['env'] = 'production';

    $this->artisan('backup:run')
        ->expectsOutputToContain('not a private bucket')
        ->assertSuccessful();
});

test('the demo seeder refuses to run in production', function () {
    $this->app['env'] = 'production';

    $before = Product::count();
    (new DemoSeeder)->run();

    expect(Product::count())->toBe($before);
});
