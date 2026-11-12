<?php

use App\Livewire\Admin\Content\SiteInfo;
use App\Settings\SiteInfoSettings;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => $this->seed(RoleSeeder::class));

function fillSiteInfo($component)
{
    return $component
        ->set('contactEmail', 'hello@shop.test')
        ->set('contactPhone', '+60 12-345 6789')
        ->set('contactWhatsapp', '+60 12-345 6789')
        ->set('contactAddress', '1 Jalan Halal, KL')
        ->set('socialFacebook', 'https://www.facebook.com/halalbizs')
        ->set('socialInstagram', 'https://instagram.com/halalbizs')
        ->set('socialTiktok', 'https://www.tiktok.com/@halalbizs')
        ->set('socialYoutube', 'https://m.youtube.com/@halalbizs')
        ->set('socialX', 'https://twitter.com/halalbizs');
}

test('a publisher saves contact and social, web footer and config show them', function () {
    fillSiteInfo(Livewire::actingAs(contentPublisher())->test(SiteInfo::class))
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SiteInfoSettings::class)->refresh()->contact_email)->toBe('hello@shop.test');

    $this->get('/')
        ->assertSee('href="mailto:hello@shop.test"', false)
        ->assertSee('href="tel:+60 12-345 6789"', false)
        ->assertSee('href="https://wa.me/60123456789"', false)
        ->assertSee('1 Jalan Halal, KL')
        ->assertSee('href="https://www.facebook.com/halalbizs"', false)
        ->assertSee('rel="noopener noreferrer"', false);

    $this->getJson('/api/v1/config')
        ->assertJsonPath('data.contact.email', 'hello@shop.test')
        ->assertJsonPath('data.contact.whatsapp', '+60 12-345 6789')
        ->assertJsonPath('data.social.x', 'https://twitter.com/halalbizs');
});

test('an editor with cms.manage only cannot save and sees no Save button', function () {
    $editor = contentEditor();

    fillSiteInfo(Livewire::actingAs($editor)->test(SiteInfo::class))
        ->call('save')
        ->assertForbidden();

    Livewire::actingAs($editor)->test(SiteInfo::class)
        ->assertSee('Only a publisher can change these.')
        ->assertDontSee('Save contact');

    expect(app(SiteInfoSettings::class)->refresh()->contact_email)->toBe('');
});

test('a social link must be https on that network', function (string $field, string $value) {
    Livewire::actingAs(contentPublisher())->test(SiteInfo::class)
        ->set($field, $value)
        ->call('save')
        ->assertHasErrors($field);
})->with([
    'http' => ['socialFacebook', 'http://facebook.com/x'],
    'wrong host' => ['socialInstagram', 'https://facebook.com/x'],
    'look-alike host' => ['socialTiktok', 'https://evil-tiktok.com/x'],
    'suffix trick' => ['socialYoutube', 'https://youtube.com.evil.test/x'],
    'not a url' => ['socialX', 'x.com/me'],
]);

test('email and phone are checked', function (string $field, string $value) {
    Livewire::actingAs(contentPublisher())->test(SiteInfo::class)
        ->set($field, $value)
        ->call('save')
        ->assertHasErrors($field);
})->with([
    'email' => ['contactEmail', 'not-an-email'],
    'phone letters' => ['contactPhone', '012abc'],
    'whatsapp too long' => ['contactWhatsapp', str_repeat('1', 21)],
]);

test('an empty form renders no contact block and null config values', function () {
    $this->get('/')->assertDontSee('mailto:', false)->assertDontSee('wa.me', false);

    $this->getJson('/api/v1/config')
        ->assertJsonPath('data.contact', ['email' => null, 'phone' => null, 'whatsapp' => null, 'address' => null])
        ->assertJsonPath('data.social.facebook', null);
});

test('only filled fields render and values are escaped', function () {
    Livewire::actingAs(contentPublisher())->test(SiteInfo::class)
        ->set('contactAddress', '<script>alert(1)</script>')
        ->call('save')
        ->assertHasNoErrors();

    $this->get('/')
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('mailto:', false);
});

test('a save writes an audit entry with only the changed fields', function () {
    $publisher = contentPublisher();

    Livewire::actingAs($publisher)->test(SiteInfo::class)
        ->set('contactEmail', 'a@shop.test')->call('save');
    Livewire::actingAs($publisher)->test(SiteInfo::class)
        ->set('contactPhone', '0123')->call('save');

    $last = Activity::where('log_name', 'site-info')->latest('id')->first();

    expect(Activity::where('log_name', 'site-info')->count())->toBe(2)
        ->and($last->causer_id)->toBe($publisher->id)
        ->and($last->attribute_changes['attributes'])->toBe(['contact_phone' => '0123'])
        ->and($last->attribute_changes['old'])->toBe(['contact_phone' => '']);
});

test('saving with nothing changed writes no audit entry', function () {
    Livewire::actingAs(contentPublisher())->test(SiteInfo::class)->call('save');

    expect(Activity::where('log_name', 'site-info')->count())->toBe(0);
});

test('the page opens for an editor', function () {
    $this->actingAs(contentEditor())->get('/admin/content/site-info')->assertOk();
});
