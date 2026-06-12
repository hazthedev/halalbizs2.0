<?php

use App\Enums\GatewayPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PayoutStatus;
use App\Enums\ReturnStatus;
use App\Enums\SubOrderStatus;
use App\Livewire\Admin\Notifications as AdminNotifications;
use App\Livewire\NotificationBell;
use App\Livewire\Seller\Notifications as SellerNotifications;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\ReturnReason;
use App\Models\ReturnRequest;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use App\Notifications\AdminAlertNotification;
use App\Notifications\NewChatMessageNotification;
use App\Services\ChatService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(RoleSeeder::class));

function notifAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function notifBuyer(): User
{
    $user = User::factory()->create();
    $user->assignRole('buyer');

    return $user;
}

function notifStore(): Store
{
    $owner = User::factory()->create();
    $owner->assignRole('seller');

    return Store::factory()->approved()->create(['user_id' => $owner->id]);
}

// ===== Chat notifications: database-first, throttled mail =====

it('notifies the other side database-first and throttles mail to one per 10 minutes', function () {
    Notification::fake();

    $buyer = notifBuyer();
    $store = notifStore();
    $chat = app(ChatService::class);
    $conversation = $chat->openConversation($buyer, $store);

    $chat->sendMessage($conversation, 'buyer', $buyer, 'First message');
    $chat->sendMessage($conversation, 'buyer', $buyer, 'Second message');

    // First message: database + mail. Second within the window: database ONLY.
    Notification::assertSentTo(
        $store->user,
        NewChatMessageNotification::class,
        fn ($notification, $channels) => $notification->message->body === 'First message' && $channels === ['database', 'mail'],
    );
    Notification::assertSentTo(
        $store->user,
        NewChatMessageNotification::class,
        fn ($notification, $channels) => $notification->message->body === 'Second message' && $channels === ['database'],
    );
});

it('throttles the mail channel per conversation side, not globally', function () {
    Notification::fake();

    $buyer = notifBuyer();
    $store = notifStore();
    $chat = app(ChatService::class);
    $conversation = $chat->openConversation($buyer, $store);

    $chat->sendMessage($conversation, 'buyer', $buyer, 'To the seller');
    $chat->sendMessage($conversation, 'seller', $store->user, 'To the buyer');

    // Each direction gets its own first mail.
    Notification::assertSentTo(
        $store->user,
        NewChatMessageNotification::class,
        fn ($notification, $channels) => $channels === ['database', 'mail'],
    );
    Notification::assertSentTo(
        $buyer,
        NewChatMessageNotification::class,
        fn ($notification, $channels) => $channels === ['database', 'mail'],
    );
});

it('writes a real database notification with a deep link for the recipient', function () {
    $buyer = notifBuyer();
    $store = notifStore();
    $chat = app(ChatService::class);
    $conversation = $chat->openConversation($buyer, $store);

    $chat->sendMessage($conversation, 'buyer', $buyer, 'Hello seller');

    $notification = $store->user->notifications()->first();

    expect($notification)->not->toBeNull()
        ->and($notification->data['message'])->toContain($buyer->name)
        ->and($notification->data['url'])->toContain('/seller/messages?conversation='.$conversation->id);

    // And the reply notifies the buyer with a buyer-side link.
    $chat->sendMessage($conversation, 'seller', $store->user, 'Hello buyer');

    expect($buyer->notifications()->first()->data['url'])
        ->toContain('/account/messages?store='.$store->id);
});

// ===== Notification bell =====

it('renders the bell with an unread dot for every role', function () {
    $buyer = notifBuyer();
    $store = notifStore();
    $admin = notifAdmin();

    foreach ([
        [$buyer, 'storefront', route('account.notifications')],
        [$store->user, 'seller', route('seller.notifications')],
        [$admin, 'admin', route('admin.notifications')],
    ] as [$user, $context, $viewAllUrl]) {
        $user->notify(new AdminAlertNotification('Bell smoke test alert', url('/')));

        Livewire::actingAs($user)
            ->test(NotificationBell::class, ['context' => $context])
            ->assertSee('Bell smoke test alert')
            ->assertSeeHtml('data-testid="bell-unread-dot"')
            ->assertSeeHtml($viewAllUrl);
    }
});

it('hides the unread dot once everything is read', function () {
    $buyer = notifBuyer();
    $buyer->notify(new AdminAlertNotification('Read me', url('/')));
    $buyer->notifications()->first()->markAsRead();

    Livewire::actingAs($buyer)
        ->test(NotificationBell::class, ['context' => 'storefront'])
        ->assertDontSeeHtml('data-testid="bell-unread-dot"');
});

it('marks a notification read and follows its url when clicked in the bell', function () {
    $buyer = notifBuyer();
    $buyer->notify(new AdminAlertNotification('Click me', 'https://example.test/target'));

    $notification = $buyer->notifications()->first();

    Livewire::actingAs($buyer)
        ->test(NotificationBell::class, ['context' => 'storefront'])
        ->call('open', $notification->id)
        ->assertRedirect('https://example.test/target');

    expect($notification->fresh()->read_at)->not->toBeNull();
});

// ===== Seller + admin full-page lists =====

it('lists, marks read and empties on the seller notifications page', function () {
    $store = notifStore();
    $store->user->notify(new AdminAlertNotification('Seller page alert', route('seller.dashboard')));

    Livewire::actingAs($store->user)
        ->test(SellerNotifications::class)
        ->assertSee('Seller page alert')
        ->call('markAllRead');

    expect($store->user->unreadNotifications()->count())->toBe(0);

    Livewire::actingAs(notifStore()->user)
        ->test(SellerNotifications::class)
        ->assertSee(__('No notifications'));
});

it('lists and follows urls on the admin notifications page', function () {
    $admin = notifAdmin();
    $admin->notify(new AdminAlertNotification('Admin page alert', route('admin.dashboard')));

    $notification = $admin->notifications()->first();

    Livewire::actingAs($admin)
        ->test(AdminNotifications::class)
        ->assertSee('Admin page alert')
        ->call('open', $notification->id)
        ->assertRedirect(route('admin.dashboard'));

    expect($notification->fresh()->read_at)->not->toBeNull();
});

// ===== Admin alerts (AdminAlertObserver) =====

it('alerts every admin when a new seller application (pending store) lands', function () {
    Notification::fake();

    $adminOne = notifAdmin();
    $adminTwo = notifAdmin();

    $store = Store::factory()->create(); // pending by default

    foreach ([$adminOne, $adminTwo] as $admin) {
        Notification::assertSentTo(
            $admin,
            AdminAlertNotification::class,
            fn ($notification) => str_contains($notification->message, $store->name)
                && $notification->url === route('admin.sellers.applications'),
        );
    }
});

it('does not alert admins when a store is created already approved (seed shape)', function () {
    Notification::fake();
    notifAdmin();

    Store::factory()->approved()->create();

    Notification::assertNothingSent();
});

it('alerts admins when a payout is requested', function () {
    Notification::fake();

    $admin = notifAdmin();
    $store = notifStore();

    Payout::create([
        'payout_no' => Payout::generatePayoutNo(),
        'store_id' => $store->id,
        'amount_sen' => 125000,
        'status' => PayoutStatus::Requested,
        'bank_snapshot' => $store->bank_details,
        'requested_at' => now(),
    ]);

    Notification::assertSentTo(
        $admin,
        AdminAlertNotification::class,
        fn ($notification) => str_contains($notification->message, 'RM 1,250.00')
            && $notification->url === route('admin.finance.payouts'),
    );
});

it('alerts admins when an iPay88 signature mismatch is flagged', function () {
    Notification::fake();

    $admin = notifAdmin();
    $order = Order::factory()->create();

    $payment = Payment::create([
        'order_id' => $order->id,
        'gateway' => PaymentMethod::Ipay88,
        'ref_no' => $order->order_no,
        'amount_sen' => $order->grand_total_sen,
        'currency' => 'MYR',
        'status' => GatewayPaymentStatus::Pending,
    ]);

    // Unrelated updates never alert.
    $payment->update(['requery_result' => '00']);
    Notification::assertNotSentTo($admin, AdminAlertNotification::class);

    $payment->update(['signature_valid' => false]);

    Notification::assertSentTo(
        $admin,
        AdminAlertNotification::class,
        fn ($notification) => str_contains($notification->message, $order->order_no)
            && $notification->url === route('admin.payments.index'),
    );
});

it('alerts admins when a return is escalated', function () {
    Notification::fake();

    $admin = notifAdmin();
    $store = notifStore();
    $order = Order::factory()->create(['user_id' => notifBuyer()->id]);

    $subOrder = SubOrder::factory()->status(SubOrderStatus::Delivered)->create([
        'order_id' => $order->id,
        'store_id' => $store->id,
    ]);

    $reason = ReturnReason::firstOrCreate(
        ['position' => 99],
        ['label' => ['en' => 'Damaged in transit'], 'is_active' => true],
    );

    $request = ReturnRequest::create([
        'sub_order_id' => $subOrder->id,
        'return_reason_id' => $reason->id,
        'status' => ReturnStatus::Requested,
        'respond_by' => now()->addHours(48),
    ]);

    Notification::assertNotSentTo($admin, AdminAlertNotification::class);

    $request->update(['status' => ReturnStatus::Escalated, 'escalated_at' => now()]);

    Notification::assertSentTo(
        $admin,
        AdminAlertNotification::class,
        fn ($notification) => str_contains($notification->message, $subOrder->sub_order_no)
            && $notification->url === route('admin.orders.returns'),
    );
});

it('stores admin alerts as database-only notifications', function () {
    $admin = notifAdmin();

    Store::factory()->create(); // pending → observer fires for real

    $notification = $admin->notifications()->first();

    expect($notification)->not->toBeNull()
        ->and($notification->type)->toBe(AdminAlertNotification::class)
        ->and($notification->data['url'])->toBe(route('admin.sellers.applications'));
});
