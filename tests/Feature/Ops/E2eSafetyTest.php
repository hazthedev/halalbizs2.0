<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

test('fixture commands refuse production without writing', function (string $command) {
    $writes = [];
    DB::listen(function ($query) use (&$writes) {
        if (preg_match('/^\s*(insert|update|delete|replace|alter|drop|create|truncate)\b/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });

    $environment = app()->environment();
    app()['env'] = 'production';
    try {
        $this->artisan($command)->assertFailed();
        expect($writes)->toBeEmpty();
    } finally {
        app()['env'] = $environment;
    }
})->with(['e2e:cod-fixture', 'e2e:seed-sale']);

test('fixture commands refuse non-QA database targets even in local env', function (string $command, array $target) {
    // RefreshDatabase already opened SQLite :memory:. Only the configuration
    // inspected by the command changes; no remote connection is ever opened.
    $connection = config('database.default');
    config(["database.connections.{$connection}" => $target]);
    $environment = app()->environment();
    app()['env'] = 'local';
    try {
        $this->artisan($command)
            ->expectsOutputToContain('Local QA database only.')
            ->assertFailed();
    } finally {
        app()['env'] = $environment;
    }
})->with(['e2e:cod-fixture', 'e2e:seed-sale'])->with([
    'remote host' => [['driver' => 'mysql', 'host' => 'db.invalid', 'database' => 'hbiz2_qa']],
    'non-QA database' => [['driver' => 'mysql', 'host' => '127.0.0.1', 'database' => 'production_fixture']],
    'URL overrides local host' => [['driver' => 'mysql', 'host' => 'localhost', 'database' => 'hbiz2_qa', 'url' => 'mysql://db.invalid/hbiz2_qa']],
    'remote writer' => [['driver' => 'mysql', 'host' => 'localhost', 'database' => 'hbiz2_qa', 'write' => ['host' => 'db.invalid']]],
    'arbitrary SQLite file' => [['driver' => 'sqlite', 'database' => '/tmp/non-qa.sqlite']],
]);

test('fixture commands are never scheduled', function () {
    $events = app(Schedule::class)->events();
    expect($events)->not->toBeEmpty();
    foreach ($events as $event) {
        expect($event->command ?? '')
            ->not->toContain('e2e:cod-fixture', 'e2e:seed-sale', 'MakeCodFixture', 'E2eSeedSale');
    }
});
