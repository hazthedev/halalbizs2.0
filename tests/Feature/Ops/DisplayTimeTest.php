<?php

use App\Enums\SubOrderStatus;
use App\Livewire\Admin\System\AuditLog;
use App\Livewire\Storefront\Account\OrderDetail;
use App\Models\Order;
use App\Models\SubOrder;
use App\Models\User;
use App\Notifications\NewDeviceLoginNotification;
use App\Support\DisplayTime;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\Finder\Finder;

// Malaysia time by DISPLAY conversion: storage + app.timezone stay UTC, every
// time a user sees is Asia/Kuala_Lumpur, every time a user types is read as it.

afterEach(fn () => Carbon::setTestNow());

test('storage stays UTC and the display zone is Kuala Lumpur', function () {
    expect(config('app.timezone'))->toBe('UTC')
        ->and(DisplayTime::zone())->toBe('Asia/Kuala_Lumpur');
});

test('a stored UTC instant displays as Malaysia time, without mutating the model value', function () {
    $stored = Carbon::parse('2026-11-03 17:30:00', 'UTC');

    expect($stored->display()->format('Y-m-d H:i'))->toBe('2026-11-04 01:30')
        ->and($stored->format('Y-m-d H:i'))->toBe('2026-11-03 17:30');
});

test('a typed Malaysia date/time is stored as UTC and round-trips back unchanged', function () {
    $utc = DisplayTime::parse('2026-11-04T01:30');

    expect($utc->timezoneName)->toBe('UTC')
        ->and($utc->toDateTimeString())->toBe('2026-11-03 17:30:00')
        ->and($utc->display()->format('Y-m-d\TH:i'))->toBe('2026-11-04T01:30');
});

test('a Malaysia day is bounded in UTC at 16:00 the evening before', function () {
    expect(DisplayTime::dayStart('2026-11-04')->toDateTimeString())->toBe('2026-11-03 16:00:00')
        ->and(DisplayTime::dayEnd('2026-11-04')->toDateTimeString())->toBe('2026-11-04 15:59:59');
});

test('today is the Malaysia date: 17:30 UTC is already tomorrow', function () {
    Carbon::setTestNow('2026-11-03 17:30:00');

    expect(DisplayTime::today()->toDateString())->toBe('2026-11-04')
        ->and(DisplayTime::dayStart()->toDateTimeString())->toBe('2026-11-03 16:00:00');

    Carbon::setTestNow('2026-11-03 15:59:00');

    expect(DisplayTime::today()->toDateString())->toBe('2026-11-03');
});

test('a day-boundary query at 23:30 MYT counts the right Malaysia day', function () {
    $late = User::factory()->create(['created_at' => '2026-11-04 15:30:00']);   // 4 Nov 23:30 MYT
    $next = User::factory()->create(['created_at' => '2026-11-04 16:30:00']);   // 5 Nov 00:30 MYT
    $early = User::factory()->create(['created_at' => '2026-11-03 16:30:00']);  // 4 Nov 00:30 MYT

    $ids = User::query()
        ->whereBetween('created_at', [DisplayTime::dayStart('2026-11-04'), DisplayTime::dayEnd('2026-11-04')])
        ->pluck('id')->all();

    expect($ids)->toContain($late->id, $early->id)->not->toContain($next->id);
});

test('the audit log date filter is a Malaysia day', function () {
    test()->seed(RoleSeeder::class);
    $admin = User::factory()->create(['two_factor_method' => 'email']);
    makeAdmin($admin);

    $late = activity()->log('late-night-event');
    $late->forceFill(['created_at' => '2026-11-04 15:30:00'])->save();    // 4 Nov 23:30 MYT
    $next = activity()->log('after-midnight-event');
    $next->forceFill(['created_at' => '2026-11-04 16:30:00'])->save();    // 5 Nov 00:30 MYT

    Livewire::actingAs($admin)
        ->test(AuditLog::class)
        ->set('dateFrom', '2026-11-04')
        ->set('dateTo', '2026-11-04')
        ->assertSee('04 Nov 2026 23:30')
        ->assertDontSee('05 Nov 2026 00:30');

    expect(Activity::count())->toBeGreaterThanOrEqual(2);
});

test('the audit log ignores an impossible calendar day filter', function () {
    Carbon::setTestNow('2026-11-04 12:00:00');
    test()->seed(RoleSeeder::class);
    $admin = User::factory()->create(['two_factor_method' => 'email']);
    makeAdmin($admin);
    activity()->log('calendar-day-filter-control')
        ->forceFill(['created_at' => '2026-11-04 10:00:00'])
        ->save();

    Livewire::actingAs($admin)
        ->test(AuditLog::class)
        ->set('dateFrom', '2026-11-31')
        ->assertSee('calendar-day-filter-control');
});

// ── 3 sample screens + 1 email: stored 2026-11-03 17:30 UTC reads 2026-11-04 01:30 ──

test('sample screen 1: the audit log shows a stored 17:30 UTC as 01:30 next day', function () {
    test()->seed(RoleSeeder::class);
    $admin = User::factory()->create(['two_factor_method' => 'email']);
    makeAdmin($admin);
    activity()->log('sample')->forceFill(['created_at' => '2026-11-03 17:30:00'])->save();

    Livewire::actingAs($admin)->test(AuditLog::class)->assertSee('04 Nov 2026 01:30');
});

test('sample screen 2: the buyer order page shows placed-at in Malaysia time', function () {
    $buyer = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $buyer->id, 'placed_at' => '2026-11-03 17:30:00']);
    $subOrder = SubOrder::factory()->status(SubOrderStatus::Confirmed)->create(['order_id' => $order->id]);

    Livewire::actingAs($buyer)->test(OrderDetail::class, ['subOrder' => $subOrder])
        ->assertSee('4 Nov 2026, 1:30 am');
});

test('sample screen 3: the invoice PDF view shows placed-at in Malaysia time', function () {
    $order = Order::factory()->create(['placed_at' => '2026-11-03 17:30:00']);
    $subOrder = SubOrder::factory()->status(SubOrderStatus::Confirmed)->create(['order_id' => $order->id]);

    expect(view('pdf.invoice', ['subOrder' => $subOrder])->render())->toContain('04 Nov 2026, 01:30');
});

test('sample email: the new-device login mail shows Malaysia time', function () {
    Carbon::setTestNow('2026-11-03 17:30:00');

    $mail = (new NewDeviceLoginNotification('Chrome on Mac', '203.0.113.9'))->toMail(User::factory()->create());

    expect(implode(' ', $mail->introLines))->toContain('4 Nov 2026, 1:30 am');
});

// ── Guard: no view or app file renders a stored timestamp without the helper ──

test('guard: every view formats a stored timestamp through display() or DisplayTime', function () {
    // `date`-cast columns are calendar dates (no clock time): nothing to convert.
    $dateOnly = 'valid_from|valid_to|halal_packed_on|halal_cert_expiry|occurred_on|last_checkin_on|last_spin_on';
    $offenders = [];

    foreach (Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $file) {
        foreach (explode("\n", $file->getContents()) as $n => $line) {
            if (! preg_match('/->(format|translatedFormat|isoFormat|toDateString|toDateTimeString|toFormattedDateString|toDayDateTimeString)\(/', $line)) {
                continue;
            }
            $stripped = preg_replace("/(?:$dateOnly)\\??->(?:format|translatedFormat|toDateString)\\([^)]*\\)/", '', $line);
            $stripped = preg_replace('/->display\(\)->(?:format|translatedFormat|isoFormat|toDateString|toDateTimeString)\([^)]*\)/', '', $stripped);
            $stripped = preg_replace('/DisplayTime::[a-z]+\([^)]*\)(?:->\w+\([^)]*\))*/', '', $stripped);

            if (preg_match('/->(format|translatedFormat|isoFormat|toDateString|toDateTimeString|toFormattedDateString|toDayDateTimeString)\(/', $stripped)) {
                $offenders[] = $file->getRelativePathname().':'.($n + 1);
            }
        }
    }

    expect($offenders)->toBe([], 'stored time rendered without ->display(): '.implode(', ', $offenders));
});

test('guard: no view or component reads "now" or "today" in UTC for the user', function () {
    $offenders = [];

    foreach (Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $file) {
        foreach (explode("\n", $file->getContents()) as $n => $line) {
            if (preg_match('/(?<![\w>:])(now|today)\(\)->(format|toDateString|toDateTimeString|translatedFormat)\(/', $line)) {
                $offenders[] = $file->getRelativePathname().':'.($n + 1);
            }
        }
    }

    expect($offenders)->toBe([], 'use DisplayTime::now(): '.implode(', ', $offenders));
});

test('guard: typed dates are parsed through DisplayTime, never Carbon::parse on user input', function () {
    $offenders = [];

    foreach (['Seller/GroupBuys', 'Seller/LiveSessions', 'Seller/Vouchers/Index', 'Seller/Products/Form', 'Admin/Content/Vouchers', 'Admin/Content/Banners', 'Admin/Content/Theme', 'Admin/Content/FlashSales'] as $class) {
        $src = file_get_contents(app_path("Livewire/{$class}.php"));
        if (preg_match('/Carbon::parse\(\s*\$(?:this->)?(?:startsAt|endsAt|value|validated)/', $src)) {
            $offenders[] = $class;
        }
    }

    expect($offenders)->toBe([]);
});
