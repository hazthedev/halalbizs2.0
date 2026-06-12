<?php

use App\Livewire\Admin\Localization\Index;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\CurrencyConverter;
use App\Settings\GeneralSettings;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

function localizationAdmin(): User
{
    test()->seed(RoleSeeder::class);

    $user = User::factory()->create(['two_factor_method' => 'email']); // admins need 2FA (EnsureAdmin)
    $user->assignRole('admin');

    return $user;
}

// ── Languages ───────────────────────────────────────────────────────────

test('toggling ms updates GeneralSettings.enabled_locales with en always present', function () {
    test()->seed(CurrencySeeder::class);
    $admin = localizationAdmin();

    expect(app(GeneralSettings::class)->enabled_locales)->toBe(['en', 'ms']);

    Livewire::actingAs($admin)->test(Index::class)->call('toggleMs');

    expect(app(GeneralSettings::class)->refresh()->enabled_locales)->toBe(['en']);

    Livewire::actingAs($admin)->test(Index::class)->call('toggleMs');

    expect(app(GeneralSettings::class)->refresh()->enabled_locales)->toBe(['en', 'ms']);
});

// ── Currencies ──────────────────────────────────────────────────────────

test('currency toggles flip is_active but the MYR base is locked on', function () {
    test()->seed(CurrencySeeder::class);
    $admin = localizationAdmin();
    $myr = Currency::where('code', 'MYR')->sole();
    $usd = Currency::where('code', 'USD')->sole();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('toggleCurrency', $usd->id)
        ->call('toggleCurrency', $myr->id);

    expect($usd->refresh()->is_active)->toBeFalse()
        ->and($myr->refresh()->is_active)->toBeTrue();
});

// ── Exchange rates (append-only) ────────────────────────────────────────

test('a manual rate update appends a new row, wins latestFor, and clears the fx cache', function () {
    test()->seed(CurrencySeeder::class);
    $admin = localizationAdmin();

    // Prime the converter cache so we can prove the update busts it.
    app(CurrencyConverter::class)->effectiveRate('USD');
    expect(Cache::has('fx:USD'))->toBeTrue();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('rateInput.USD', '0.22')
        ->set('marginInput.USD', '1.5')
        ->call('updateRate', 'USD')
        ->assertHasNoErrors();

    expect(ExchangeRate::where('currency_code', 'USD')->count())->toBe(2)
        ->and(Cache::has('fx:USD'))->toBeFalse();

    $latest = ExchangeRate::latestFor('USD');

    expect((string) $latest->rate)->toBe('0.22000000')
        ->and((string) $latest->margin_percent)->toBe('1.50')
        ->and($latest->source)->toBe('manual');
});

test('a zero or malformed rate is rejected and nothing is written', function () {
    test()->seed(CurrencySeeder::class);
    $admin = localizationAdmin();
    $before = ExchangeRate::count();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('rateInput.USD', '0')
        ->call('updateRate', 'USD')
        ->assertHasErrors(['rateInput.USD'])
        ->set('rateInput.USD', 'abc')
        ->call('updateRate', 'USD')
        ->assertHasErrors(['rateInput.USD']);

    expect(ExchangeRate::count())->toBe($before);
});

// ── Access control ──────────────────────────────────────────────────────

test('non-admins get 403 on the localization screen', function () {
    test()->seed(RoleSeeder::class);
    $buyer = User::factory()->create();

    test()->actingAs($buyer)->get(route('admin.localization'))->assertForbidden();
});

test('admins can open the localization screen', function () {
    test()->seed(CurrencySeeder::class);

    test()->actingAs(localizationAdmin())
        ->get(route('admin.localization'))
        ->assertOk()
        ->assertSee('Exchange rates');
});
