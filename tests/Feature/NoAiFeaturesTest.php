<?php

use App\Enums\StoreStatus;
use App\Livewire\Seller\Products\Form;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

test('the storefront offers no AI assistant', function () {
    $this->get(route('home'))->assertOk()
        ->assertDontSee('concierge', false)
        ->assertDontSee('shopping assistant', false);
});

test('no Anthropic config or AI classes remain', function () {
    expect(config('services.anthropic'))->toBeNull()
        ->and(class_exists('App\Services\Ai\ClaudeClient'))->toBeFalse()
        ->and(class_exists('App\Livewire\Storefront\ShopAssistant'))->toBeFalse();
});

test('the product form has no AI description generator', function () {
    $this->seed(RoleSeeder::class);
    $seller = User::factory()->create();
    $seller->assignRole('seller');
    Store::factory()->create(['user_id' => $seller->id, 'status' => StoreStatus::Approved]);

    Livewire::actingAs($seller)->test(Form::class)
        ->assertDontSee('Generate with AI')
        ->assertDontSeeHtml('generateCopy');

    expect(method_exists(Form::class, 'generateCopy'))->toBeFalse();
});
