<?php

use App\Models\User;
use App\Notifications\AdminAlertNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Scheduling\Schedule;
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
})->with(['backup:run', 'certificates:watch-expiry', 'ledger:release-holds', 'orders:cancel-unshipped']);

test('no ops address: admins get the bell, no mail', function () {
    $this->seed(RoleSeeder::class);
    config(['app.ops_alert_email' => null]);
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
