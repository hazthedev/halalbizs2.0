<?php

use Illuminate\Support\Facades\Http;

/**
 * The one behaviour worth pinning: a model reply that drops a :placeholder must
 * be REFUSED, not written. Nothing at runtime catches that — the shopper just
 * reads ":amount" — so if this test stops failing against a broken guard, the
 * guard is gone.
 */
function fakeLangFile(string $locale, array $strings): string
{
    $path = lang_path("{$locale}.json");
    file_put_contents($path, json_encode($strings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    return $path;
}

function fakeClaudeReturning(array $map): void
{
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'text', 'text' => json_encode($map, JSON_UNESCAPED_UNICODE)]],
    ])]);
}

beforeEach(function () {
    config(['services.anthropic.key' => 'test-key']);
});

afterEach(function () {
    @unlink(lang_path('zz.json'));
});

test('a translation that drops a placeholder is refused and left in English', function () {
    $path = fakeLangFile('zz', [
        ':amount off' => ':amount off',
        'Add to cart' => 'Add to cart',
    ]);

    fakeClaudeReturning([
        ':amount off' => 'Giam gia',        // placeholder LOST — must be refused
        'Add to cart' => 'Them vao gio',    // clean — must be kept
    ]);

    $this->artisan('translate:lang', ['locale' => 'zz'])
        ->expectsOutputToContain('Translated 1. Refused 1')
        ->assertSuccessful();

    $after = json_decode(file_get_contents($path), true);

    expect($after[':amount off'])->toBe(':amount off')
        ->and($after['Add to cart'])->toBe('Them vao gio');
});

test('already-translated strings are left alone', function () {
    $path = fakeLangFile('zz', ['Add to cart' => 'Sudah diterjemah']);

    fakeClaudeReturning(['Add to cart' => 'SHOULD NOT BE USED']);

    $this->artisan('translate:lang', ['locale' => 'zz'])
        ->expectsOutputToContain('fully translated')
        ->assertSuccessful();

    expect(json_decode(file_get_contents($path), true)['Add to cart'])->toBe('Sudah diterjemah');
});

test('a string the model invented is ignored', function () {
    $path = fakeLangFile('zz', ['Add to cart' => 'Add to cart']);

    fakeClaudeReturning([
        'Add to cart' => 'Them vao gio',
        'Some string we never sent' => 'Injected',
    ]);

    $this->artisan('translate:lang', ['locale' => 'zz'])->assertSuccessful();

    expect(json_decode(file_get_contents($path), true))->toBe(['Add to cart' => 'Them vao gio']);
});

test('it fails loudly rather than silently when no API key is configured', function () {
    fakeLangFile('zz', ['Add to cart' => 'Add to cart']);
    config(['services.anthropic.key' => null]);

    $this->artisan('translate:lang', ['locale' => 'zz'])
        ->expectsOutputToContain('ANTHROPIC_API_KEY is not set')
        ->assertFailed();
});
