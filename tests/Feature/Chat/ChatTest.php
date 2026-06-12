<?php

use App\Livewire\Seller\Messages as SellerMessages;
use App\Livewire\Storefront\Account\Messages as BuyerMessages;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\ChatService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(RoleSeeder::class));

function chatBuyer(): User
{
    $user = User::factory()->create();
    $user->assignRole('buyer');

    return $user;
}

function chatStore(): Store
{
    $owner = User::factory()->create();
    $owner->assignRole('seller');

    return Store::factory()->approved()->create(['user_id' => $owner->id]);
}

// ===== Opening conversations =====

it('opens a conversation once per buyer+store pair', function () {
    $buyer = chatBuyer();
    $store = chatStore();
    $chat = app(ChatService::class);

    $first = $chat->openConversation($buyer, $store);
    $second = $chat->openConversation($buyer, $store);

    expect($second->id)->toBe($first->id)
        ->and(Conversation::count())->toBe(1)
        ->and($first->buyer_id)->toBe($buyer->id)
        ->and($first->store_id)->toBe($store->id);
});

it('opens or creates the conversation from the ?store entry point', function () {
    $buyer = chatBuyer();
    $store = chatStore();

    $this->actingAs($buyer)
        ->get(route('account.messages', ['store' => $store->id]))
        ->assertOk()
        ->assertSee($store->name);

    expect(Conversation::where('buyer_id', $buyer->id)->where('store_id', $store->id)->exists())->toBeTrue();
});

it('shows the product context chip in the composer from the ?product entry point', function () {
    $buyer = chatBuyer();
    $store = chatStore();
    $product = Product::factory()->create(['store_id' => $store->id]);

    $this->actingAs($buyer)
        ->get(route('account.messages', ['store' => $store->id, 'product' => $product->id]))
        ->assertOk()
        ->assertSee(__('Asking about'))
        ->assertSee($product->getTranslation('name', 'en'));
});

it('redirects guests from PDP chat to login', function () {
    $product = Product::factory()->create();

    $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertSee(route('login'));
});

it('links the PDP chat buttons to the messages entry point for buyers', function () {
    $buyer = chatBuyer();
    $product = Product::factory()->create();

    $this->actingAs($buyer)
        ->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertSee('account/messages?store='.$product->store_id, false);
});

// ===== Own-shop guard =====

it('blocks a seller from chatting with their own shop', function () {
    $store = chatStore();

    expect(fn () => app(ChatService::class)->openConversation($store->user, $store))
        ->toThrow(ValidationException::class);

    expect(Conversation::count())->toBe(0);
});

it('does not open a conversation when the owner hits their own ?store entry point', function () {
    $store = chatStore();

    $this->actingAs($store->user)
        ->get(route('account.messages', ['store' => $store->id]))
        ->assertOk();

    expect(Conversation::count())->toBe(0);
});

// ===== Sending & receiving =====

it('lets buyer and seller exchange messages through their components', function () {
    Notification::fake();

    $buyer = chatBuyer();
    $store = chatStore();
    $conversation = app(ChatService::class)->openConversation($buyer, $store);

    Livewire::actingAs($buyer)
        ->test(BuyerMessages::class)
        ->call('openConversation', $conversation->id)
        ->set('body', 'Is this kuih still fresh today?')
        ->call('send')
        ->assertHasNoErrors()
        ->assertSee('Is this kuih still fresh today?');

    Livewire::actingAs($store->user)
        ->test(SellerMessages::class)
        ->call('openConversation', $conversation->id)
        ->assertSee('Is this kuih still fresh today?')
        ->set('body', 'Baked this morning!')
        ->call('send')
        ->assertHasNoErrors()
        ->assertSee('Baked this morning!');

    expect($conversation->messages()->count())->toBe(2)
        ->and($conversation->messages()->where('sender_type', 'buyer')->count())->toBe(1)
        ->and($conversation->messages()->where('sender_type', 'seller')->count())->toBe(1)
        ->and($conversation->refresh()->last_message_at)->not->toBeNull();
});

it('validates the message body on both bounds', function () {
    $buyer = chatBuyer();
    $store = chatStore();
    $conversation = app(ChatService::class)->openConversation($buyer, $store);

    Livewire::actingAs($buyer)
        ->test(BuyerMessages::class)
        ->call('openConversation', $conversation->id)
        ->set('body', '')
        ->call('send')
        ->assertHasErrors(['body' => 'required'])
        ->set('body', str_repeat('a', 2001))
        ->call('send')
        ->assertHasErrors(['body' => 'max']);

    expect(Message::count())->toBe(0);
});

it('rejects blank and over-long bodies at the service layer too', function () {
    $buyer = chatBuyer();
    $store = chatStore();
    $chat = app(ChatService::class);
    $conversation = $chat->openConversation($buyer, $store);

    expect(fn () => $chat->sendMessage($conversation, 'buyer', $buyer, '   '))
        ->toThrow(ValidationException::class);
    expect(fn () => $chat->sendMessage($conversation, 'buyer', $buyer, str_repeat('a', 2001)))
        ->toThrow(ValidationException::class);
});

// ===== Unread counts & mark-read =====

it('tracks unread counts per side and clears them on open', function () {
    Notification::fake();

    $buyer = chatBuyer();
    $store = chatStore();
    $chat = app(ChatService::class);
    $conversation = $chat->openConversation($buyer, $store);

    $chat->sendMessage($conversation, 'seller', $store->user, 'Your order shipped!');
    $chat->sendMessage($conversation, 'seller', $store->user, 'Tracking follows shortly.');
    $chat->sendMessage($conversation, 'buyer', $buyer, 'Thanks!');

    expect($conversation->unreadCountFor('buyer'))->toBe(2)
        ->and($conversation->unreadCountFor('seller'))->toBe(1);

    // Buyer opens the thread → seller messages read; their own stay untouched.
    Livewire::actingAs($buyer)
        ->test(BuyerMessages::class)
        ->call('openConversation', $conversation->id);

    expect($conversation->unreadCountFor('buyer'))->toBe(0)
        ->and($conversation->unreadCountFor('seller'))->toBe(1);

    $chat->markRead($conversation, 'seller');

    expect($conversation->unreadCountFor('seller'))->toBe(0);
});

// ===== Leakage =====

it('never shows another store its conversations (seller leakage)', function () {
    Notification::fake();

    $buyer = chatBuyer();
    $storeA = chatStore();
    $storeB = chatStore();
    $chat = app(ChatService::class);

    $conversationA = $chat->openConversation($buyer, $storeA);
    $chat->sendMessage($conversationA, 'buyer', $buyer, 'Secret question for store A');

    Livewire::actingAs($storeB->user)
        ->test(SellerMessages::class)
        ->assertDontSee('Secret question for store A')
        ->assertDontSee($buyer->name)
        ->call('openConversation', $conversationA->id)
        ->assertSet('conversationId', null)
        ->assertDontSee('Secret question for store A');
});

it('never shows another buyer someone else\'s conversations (buyer leakage)', function () {
    Notification::fake();

    $buyerA = chatBuyer();
    $buyerB = chatBuyer();
    $store = chatStore();
    $chat = app(ChatService::class);

    $conversationA = $chat->openConversation($buyerA, $store);
    $chat->sendMessage($conversationA, 'buyer', $buyerA, 'Buyer A private note');

    Livewire::actingAs($buyerB)
        ->test(BuyerMessages::class)
        ->assertDontSee('Buyer A private note')
        ->call('openConversation', $conversationA->id)
        ->assertSet('conversationId', null)
        ->assertDontSee('Buyer A private note');
});

// ===== Product context chip =====

it('renders the product context chip linking to the PDP', function () {
    Notification::fake();

    $buyer = chatBuyer();
    $store = chatStore();
    $product = Product::factory()->create(['store_id' => $store->id]);
    $chat = app(ChatService::class);

    $conversation = $chat->openConversation($buyer, $store);
    $chat->sendMessage($conversation, 'buyer', $buyer, 'Is this available in blue?', $product);

    expect($conversation->messages()->first()->product_id)->toBe($product->id);

    Livewire::actingAs($store->user)
        ->test(SellerMessages::class)
        ->call('openConversation', $conversation->id)
        ->assertSeeHtml('data-testid="chat-context-chip"')
        ->assertSee($product->getTranslation('name', 'en'))
        ->assertSeeHtml(route('product.show', $product->slug));
});

it('keeps the message readable after the context product is deleted', function () {
    Notification::fake();

    $buyer = chatBuyer();
    $store = chatStore();
    $product = Product::factory()->create(['store_id' => $store->id]);
    $chat = app(ChatService::class);

    $conversation = $chat->openConversation($buyer, $store);
    $message = $chat->sendMessage($conversation, 'buyer', $buyer, 'About this one', $product);

    $product->forceDelete();

    expect($message->refresh()->product_id)->toBeNull()
        ->and($message->body)->toBe('About this one');
});
