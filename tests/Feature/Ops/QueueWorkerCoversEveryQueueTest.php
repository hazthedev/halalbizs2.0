<?php

use Illuminate\Console\Scheduling\Schedule;

// H-5: the scheduled worker had no --queue, so it drained `default` and nothing
// else — ConfirmIpay88PaymentJob sits on `payments`, i.e. paid orders were never
// fulfilled. The instance is one missing flag; the CLASS is a queue name that
// exists in app/ and not in the schedule. This asserts the two never drift.
test('the scheduled worker names every queue the app actually dispatches to', function () {
    $command = collect(app(Schedule::class)->events())
        ->map(fn ($event) => $event->command ?? '')
        ->first(fn (string $c) => str_contains($c, 'queue:work'));

    $this->assertNotNull($command, 'no queue:work is scheduled at all');

    preg_match('/--queue=(\S+)/', $command, $m);
    $covered = explode(',', $m[1] ?? '');

    // Both ways a queue gets named: ->onQueue('x') in a job, $queue = 'x' on a
    // queued listener. Read off the source rather than a hand-kept list, so a
    // new queue is caught the day it is added.
    $used = collect(
        \Symfony\Component\Finder\Finder::create()->files()->in(app_path())->name('*.php')
    )->flatMap(function ($file): array {
        preg_match_all('/onQueue\(\s*[\'"](\w+)[\'"]|\$queue\s*=\s*[\'"](\w+)[\'"]/', $file->getContents(), $hits);

        return array_filter(array_merge($hits[1], $hits[2]));
    })->unique()->values();

    expect($used)->not->toBeEmpty();

    // `default` carries everything that names no queue, so it must be drained too.
    $missing = array_values(array_diff($used->push('default')->unique()->all(), $covered));

    $this->assertSame([], $missing, 'dispatched to but never drained: '.implode(', ', $missing));
});
