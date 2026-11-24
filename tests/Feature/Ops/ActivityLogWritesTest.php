<?php

use App\Enums\StoreStatus;
use App\Models\Store;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

// OBS-04: the audit trail must keep writing. activity_log had no row after
// 2026-11-19 on live; these pin the three ways a row is written.

test('logging is on in config', function () {
    expect(config('activitylog.enabled'))->toBeTrue();
});

test('a model event writes a row (Store status change)', function () {
    $store = Store::factory()->create(['status' => StoreStatus::Pending]);
    Activity::query()->delete();

    $store->forceFill(['status' => StoreStatus::Approved])->save();

    expect(Activity::query()->where('subject_type', $store->getMorphClass())->where('subject_id', $store->id)->count())->toBe(1);
});

test('the activity() helper writes a row with its causer', function () {
    $admin = User::factory()->create();

    activity()->causedBy($admin)->log('obs-04 probe');

    $row = Activity::query()->where('description', 'obs-04 probe')->first();
    expect($row)->not->toBeNull()->and($row->causer_id)->toBe($admin->id);
});
