<?php

use App\Livewire\Storefront\ShopAssistant;
use App\Models\Product;
use Livewire\Livewire;

beforeEach(function () {
    config(['scout.driver' => 'collection']);
    config(['services.anthropic.key' => null]); // deterministic fallback
});

test('the concierge mounts on the storefront layout when enabled', function () {
    config(['services.concierge.enabled' => true]);

    $this->get(route('home'))->assertOk()->assertSeeLivewire(ShopAssistant::class);
});

test('it can be disabled by config', function () {
    config(['services.concierge.enabled' => false]);

    $this->get(route('home'))->assertOk()->assertDontSeeLivewire(ShopAssistant::class);
});

test('the mobile header exposes a concierge entry point that opens the panel', function () {
    config(['services.concierge.enabled' => true]);

    // The header trigger is the only element that *dispatches* open-concierge
    // (the global panel merely listens for it), so this proves the mobile entry
    // point is wired.
    $this->get(route('home'))
        ->assertOk()
        ->assertSee("\$dispatch('open-concierge')", false);
});

test('the header concierge trigger is gone when the feature is disabled', function () {
    config(['services.concierge.enabled' => false]);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee("\$dispatch('open-concierge')", false);
});

test('sending a message appends the buyer turn and an assistant reply with products', function () {
    Product::factory()->create(['name' => ['en' => 'Acacia Halal Honey', 'ms' => 'Madu Halal Acacia']]);

    Livewire::test(ShopAssistant::class)
        ->set('draft', 'honey')
        ->call('send')
        ->assertSet('draft', '')
        ->assertSee('honey')               // the buyer's message
        ->assertSee('Acacia Halal Honey'); // the recommended product card
});

test('an over-long message is rejected by validation', function () {
    Livewire::test(ShopAssistant::class)
        ->set('draft', str_repeat('a', 501))
        ->call('send')
        ->assertHasErrors('draft');
});

test('clearing the chat empties the transcript', function () {
    Product::factory()->create(['name' => ['en' => 'Acacia Halal Honey', 'ms' => 'Madu Halal Acacia']]);

    Livewire::test(ShopAssistant::class)
        ->set('draft', 'honey')
        ->call('send')
        ->assertCount('history', 2)
        ->call('clearChat')
        ->assertCount('history', 0);
});
