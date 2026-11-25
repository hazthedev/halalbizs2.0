<?php

use App\Models\User;
use App\Notifications\AdminAlertNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

function failTask(string $command): void
{
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, $command));
    expect($event)->not->toBeNull();
    $event->exitCode = 1;
    $event->callAfterCallbacks(app());
}

test('failed task mails the ops address once', function (string $command) {
    config(['app.ops_alert_email' => 'ops@example.test', 'mail.default' => 'array']);

    failTask($command);

    $sent = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
    expect($sent)->toHaveCount(1)
        ->and($sent[0]->getOriginalMessage()->getTo()[0]->getAddress())->toBe('ops@example.test')
        ->and($sent[0]->getOriginalMessage()->getSubject())->toContain($command);
})->with([
    'backup:run', 'certificates:watch-expiry', 'ledger:release-holds', 'orders:cancel-unshipped',
    // G249 (HB-DEC-1125b): every job that moves money or order state.
    'orders:expire-unpaid', 'orders:auto-complete', 'returns:auto-escalate', 'coins:expire',
    'affiliates:lock-commissions', 'subscriptions:process',
]);

test('G249: a task failing all day alerts once per Malaysia day, and logs every failure', function () {
    config(['app.ops_alert_email' => 'ops@example.test', 'mail.default' => 'array']);
    Log::spy();
    $mails = fn () => app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->count();

    $this->travelTo(Carbon\Carbon::parse('2026-11-25 01:00', 'UTC')); // 09:00 MYT
    failTask('orders:expire-unpaid');
    failTask('orders:expire-unpaid');
    $this->travelTo(Carbon\Carbon::parse('2026-11-25 15:30', 'UTC')); // 23:30 MYT, same day
    failTask('orders:expire-unpaid');
    expect($mails())->toBe(1);

    failTask('coins:expire'); // another job has its own cap
    expect($mails())->toBe(2);

    $this->travelTo(Carbon\Carbon::parse('2026-11-25 16:30', 'UTC')); // 00:30 MYT, next day
    failTask('orders:expire-unpaid');
    expect($mails())->toBe(3);

    Log::shouldHaveReceived('error')->with('Scheduled task failed: orders:expire-unpaid ('.config('app.name').')')->times(4);
});

test('no ops address: admins get the bell, no mail', function () {
    $this->seed(RoleSeeder::class);
    config(['app.ops_alert_email' => null]);
    Notification::fake();
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    failTask('backup:run');

    Notification::assertSentTo($admin, AdminAlertNotification::class);
});

test('mail down: admins still get the bell', function () {
    $this->seed(RoleSeeder::class);
    config(['app.ops_alert_email' => 'ops@example.test']);
    Mail::shouldReceive('raw')->andThrow(new RuntimeException('smtp down'));
    Notification::fake();
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    failTask('backup:run');

    Notification::assertSentTo($admin, AdminAlertNotification::class);
});

test('a task that succeeds sends nothing', function () {
    config(['app.ops_alert_email' => 'ops@example.test', 'mail.default' => 'array']);
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'backup:run'));
    $event->exitCode = 0;
    $event->callAfterCallbacks(app());

    expect(app('mail.manager')->mailer('array')->getSymfonyTransport()->messages())->toHaveCount(0);
});

test('G249: a cache that throws still sends the alert and never breaks the scheduler', function () {
    config(['app.ops_alert_email' => 'ops@example.test', 'mail.default' => 'array']);
    Illuminate\Support\Facades\Cache::shouldReceive('add')->andThrow(new RuntimeException('cache down'));

    failTask('orders:expire-unpaid');

    expect(app('mail.manager')->mailer('array')->getSymfonyTransport()->messages())->toHaveCount(1);
});
