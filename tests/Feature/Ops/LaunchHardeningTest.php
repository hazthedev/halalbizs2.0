<?php

use App\Http\Middleware\SecurityHeaders;
use App\Models\Product;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    // Point the (already-open) sqlite connection's config at a real temp file —
    // backup:run only reads the file, it doesn't reopen the connection.
    $tmp = tempnam(sys_get_temp_dir(), 'dbbk').'.sqlite';
    file_put_contents($tmp, 'sqlite-bytes');
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $tmp]);

    $this->artisan('backup:run')->assertSuccessful();

    $files = collect(Storage::disk('local')->files('backups'));
    expect($files->filter(fn ($f) => str_contains($f, 'db-'))->count())->toBe(1)
        ->and($files->filter(fn ($f) => str_contains($f, 'env-'))->count())->toBe(1);

    @unlink($tmp);
});

test('the demo seeder refuses to run in production', function () {
    $this->app['env'] = 'production';

    $before = Product::count();
    (new DemoSeeder)->run();

    expect(Product::count())->toBe($before);
});
