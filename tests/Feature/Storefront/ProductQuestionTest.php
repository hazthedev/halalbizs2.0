<?php

use App\Livewire\Seller\Questions\Index as SellerQuestions;
use App\Livewire\Storefront\ProductQuestions;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\Store;
use App\Models\User;
use App\Notifications\ProductQuestionAnswered;
use App\Notifications\ProductQuestionAsked;
use Livewire\Livewire;
use Illuminate\Support\Facades\Notification;

function makeQuestion(Product $product, ?User $asker = null, array $attributes = []): ProductQuestion
{
    return ProductQuestion::create(array_merge([
        'product_id' => $product->id,
        'store_id' => $product->store_id,
        'user_id' => ($asker ?? User::factory()->create())->id,
        'question' => 'A genuine product question here',
    ], $attributes));
}

test('a logged-in buyer can ask a question and the seller is notified', function () {
    Notification::fake();

    $store = Store::factory()->create();
    $product = Product::factory()->create(['store_id' => $store->id]);
    $buyer = User::factory()->create();

    Livewire::actingAs($buyer)
        ->test(ProductQuestions::class, ['product' => $product])
        ->set('question', 'Is this product halal certified?')
        ->call('ask')
        ->assertHasNoErrors();

    expect(ProductQuestion::where('product_id', $product->id)->where('user_id', $buyer->id)->count())->toBe(1);
    Notification::assertSentTo($store->user, ProductQuestionAsked::class);
});

test('a guest asking is redirected to login and nothing is created', function () {
    $product = Product::factory()->create();

    Livewire::test(ProductQuestions::class, ['product' => $product])
        ->set('question', 'A perfectly valid question')
        ->call('ask')
        ->assertRedirect(route('login'));

    expect(ProductQuestion::count())->toBe(0);
});

test('a too-short question is rejected', function () {
    $product = Product::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(ProductQuestions::class, ['product' => $product])
        ->set('question', 'short')
        ->call('ask')
        ->assertHasErrors('question');

    expect(ProductQuestion::count())->toBe(0);
});

test('the seller answers a question on their product and the asker is notified once', function () {
    Notification::fake();

    $store = Store::factory()->create();
    $product = Product::factory()->create(['store_id' => $store->id]);
    $buyer = User::factory()->create();
    $question = makeQuestion($product, $buyer, ['question' => 'When does it ship?']);

    Livewire::actingAs($store->user)
        ->test(SellerQuestions::class)
        ->call('startAnswer', $question->id)
        ->set('answerText', 'Ships within 2 working days.')
        ->call('saveAnswer')
        ->assertHasNoErrors();

    expect($question->fresh()->answer)->toBe('Ships within 2 working days.')
        ->and($question->fresh()->answered_at)->not->toBeNull();
    Notification::assertSentTo($buyer, ProductQuestionAnswered::class);
});

test('a seller cannot see another store\'s questions', function () {
    $storeA = Store::factory()->create();
    $productA = Product::factory()->create(['store_id' => $storeA->id]);
    makeQuestion($productA, null, ['question' => 'PrivateToStoreAOnly']);

    $storeB = Store::factory()->create();

    Livewire::actingAs($storeB->user)
        ->test(SellerQuestions::class)
        ->assertDontSee('PrivateToStoreAOnly');
});

test('hiding a question removes it from the public list', function () {
    $store = Store::factory()->create();
    $product = Product::factory()->create(['store_id' => $store->id]);
    $question = makeQuestion($product, null, ['question' => 'SoonToBeHidden']);

    Livewire::actingAs($store->user)
        ->test(SellerQuestions::class)
        ->call('hide', $question->id);

    expect($question->fresh()->is_hidden)->toBeTrue();

    Livewire::test(ProductQuestions::class, ['product' => $product])
        ->assertDontSee('SoonToBeHidden');
});

test('only non-hidden questions render on the storefront', function () {
    $product = Product::factory()->create();
    makeQuestion($product, null, ['question' => 'VisibleOne']);
    makeQuestion($product, null, ['question' => 'HiddenOne', 'is_hidden' => true]);

    Livewire::test(ProductQuestions::class, ['product' => $product])
        ->assertSee('VisibleOne')
        ->assertDontSee('HiddenOne');
});
