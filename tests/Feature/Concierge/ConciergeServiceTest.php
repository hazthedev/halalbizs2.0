<?php

use App\Models\Product;
use App\Services\ConciergeService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // The concierge searches via Scout; opt into the collection engine
    // (phpunit pins SCOUT_DRIVER=null for the suite).
    config(['scout.driver' => 'collection']);
});

test('the offline fallback recommends live products matching the query', function () {
    config(['services.anthropic.key' => null]); // no key → deterministic fallback

    Product::factory()->create(['name' => ['en' => 'Acacia Halal Honey', 'ms' => 'Madu Halal Acacia']]);
    Product::factory()->create(['name' => ['en' => 'Plain Leather Wallet', 'ms' => 'Dompet Kulit']]);

    $reply = app(ConciergeService::class)->reply('honey');

    expect($reply->products)->toHaveCount(1)
        ->and($reply->products->first()->getTranslation('name', 'en'))->toBe('Acacia Halal Honey')
        ->and($reply->text)->not->toBe('');
});

test('the fallback returns a helpful note when nothing matches', function () {
    config(['services.anthropic.key' => null]);

    Product::factory()->create(['name' => ['en' => 'Acacia Halal Honey', 'ms' => 'Madu Halal Acacia']]);

    $reply = app(ConciergeService::class)->reply('snowboard', [], 'ms');

    expect($reply->products)->toBeEmpty()
        ->and($reply->text)->toContain('Maaf');
});

test('an empty message returns an empty reply without searching', function () {
    config(['services.anthropic.key' => null]);

    $reply = app(ConciergeService::class)->reply('   ');

    expect($reply->text)->toBe('')->and($reply->products)->toBeEmpty();
});

test('the Claude path runs the search tool and returns its final answer plus products', function () {
    config(['services.anthropic.key' => 'test-key']);

    $honey = Product::factory()->create(['name' => ['en' => 'Acacia Halal Honey', 'ms' => 'Madu Halal Acacia']]);

    // First call asks to use the tool; second call (after the tool result) answers.
    Http::fakeSequence()
        ->push([
            'id' => 'msg_1',
            'role' => 'assistant',
            'content' => [
                ['type' => 'text', 'text' => 'Let me check the catalogue.'],
                ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'search_products', 'input' => ['query' => 'honey']],
            ],
            'stop_reason' => 'tool_use',
        ])
        ->push([
            'id' => 'msg_2',
            'role' => 'assistant',
            'content' => [['type' => 'text', 'text' => 'I found Acacia Halal Honey for you.']],
            'stop_reason' => 'end_turn',
        ]);

    $reply = app(ConciergeService::class)->reply('show me honey');

    expect($reply->text)->toContain('Acacia Halal Honey')
        ->and($reply->products->modelKeys())->toContain($honey->id);

    // Exactly two round-trips: tool call + final answer.
    Http::assertSentCount(2);
});

test('a Claude transport failure degrades to the search fallback', function () {
    config(['services.anthropic.key' => 'test-key']);

    Product::factory()->create(['name' => ['en' => 'Acacia Halal Honey', 'ms' => 'Madu Halal Acacia']]);

    Http::fake(['api.anthropic.com/*' => Http::response('upstream error', 500)]);

    $reply = app(ConciergeService::class)->reply('honey');

    // Fell back to Scout search rather than throwing.
    expect($reply->products->modelKeys())->not->toBeEmpty();
});
