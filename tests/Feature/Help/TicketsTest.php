<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Livewire\Admin\Support\Tickets as AdminTickets;
use App\Livewire\Storefront\Help\Tickets;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\TicketRepliedNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

function ticketsAdmin(): User
{
    test()->seed(RoleSeeder::class);

    $user = User::factory()->create(['two_factor_method' => 'email']); // admins need 2FA (EnsureAdmin)
    $user->assignRole('admin');

    return $user;
}

function makeTicket(User $user, string $subject = 'My parcel never arrived', TicketStatus $status = TicketStatus::Open): SupportTicket
{
    $ticket = $user->tickets()->create([
        'subject' => $subject,
        'status' => $status,
        'priority' => TicketPriority::Normal,
    ]);

    $ticket->replies()->create([
        'author_type' => 'user',
        'author_id' => $user->id,
        'body' => 'Order MP123 was marked delivered but nothing arrived at my address.',
    ]);

    return $ticket;
}

// ── Access ──────────────────────────────────────────────────────────────

test('guests are redirected to login from /support', function () {
    $this->get('/support')->assertRedirect(route('login'));
});

// ── Creating tickets ────────────────────────────────────────────────────

test('user creates a ticket with an opening message', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Tickets::class)
        ->call('startTicket')
        ->set('subject', 'Order MP123 never arrived')
        ->set('body', 'It was marked delivered three days ago but nothing came. Please help.')
        ->call('create')
        ->assertHasNoErrors();

    $ticket = SupportTicket::sole();

    expect($ticket->user_id)->toBe($user->id)
        ->and($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->priority)->toBe(TicketPriority::Normal)
        ->and($ticket->replies)->toHaveCount(1)
        ->and($ticket->replies->first()->author_type)->toBe('user')
        ->and($ticket->replies->first()->author_id)->toBe($user->id);
});

test('ticket subject and body lengths are validated', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Tickets::class)
        ->call('startTicket')
        ->set('subject', 'Hi')
        ->set('body', 'Too short.')
        ->call('create')
        ->assertHasErrors(['subject' => 'min', 'body' => 'min']);

    expect(SupportTicket::count())->toBe(0);
});

test('html is stripped from ticket subject and messages', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Tickets::class)
        ->call('startTicket')
        ->set('subject', 'Refund <script>alert(1)</script> question')
        ->set('body', 'Where is my <b>refund</b>? It has been more than seven days now.')
        ->call('create')
        ->assertHasNoErrors();

    $ticket = SupportTicket::sole();

    expect($ticket->subject)->toBe('Refund alert(1) question')
        ->and($ticket->replies->first()->body)->not->toContain('<b>');
});

// ── Reply round-trip ────────────────────────────────────────────────────

test('admin reply marks the ticket answered and notifies the user', function () {
    Notification::fake();
    $admin = ticketsAdmin();
    $user = User::factory()->create();
    $ticket = makeTicket($user);

    Livewire::actingAs($admin)
        ->test(AdminTickets::class)
        ->call('select', $ticket->id)
        ->set('replyBody', 'We have contacted the courier and will update you within 24 hours.')
        ->call('reply')
        ->assertHasNoErrors();

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Answered)
        ->and($ticket->replies)->toHaveCount(2)
        ->and($ticket->replies->last()->author_type)->toBe('admin')
        ->and($ticket->replies->last()->author_id)->toBe($admin->id);

    Notification::assertSentTo($user, TicketRepliedNotification::class, function ($notification, $channels) use ($ticket) {
        return $notification->ticket->is($ticket) && $channels === ['database', 'mail'];
    });
});

test('a user reply puts the ticket back to open', function () {
    $user = User::factory()->create();
    $ticket = makeTicket($user, status: TicketStatus::Answered);

    Livewire::actingAs($user)
        ->test(Tickets::class)
        ->call('select', $ticket->id)
        ->set('replyBody', 'Thanks, but the courier still has not called me back.')
        ->call('reply')
        ->assertHasNoErrors();

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->replies)->toHaveCount(2);
});

test('user closes their own ticket and can no longer reply', function () {
    $user = User::factory()->create();
    $ticket = makeTicket($user);

    $component = Livewire::actingAs($user)
        ->test(Tickets::class)
        ->call('select', $ticket->id)
        ->call('close');

    expect($ticket->refresh()->status)->toBe(TicketStatus::Closed);

    $component
        ->set('replyBody', 'One more thing I forgot to mention earlier.')
        ->call('reply');

    expect($ticket->refresh()->replies)->toHaveCount(1); // no reply added
});

// ── Leakage ─────────────────────────────────────────────────────────────

test('users never see another user\'s tickets', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $ticket = makeTicket($alice, subject: 'Alice secret ticket subject');

    // Bob's list doesn't include it.
    Livewire::actingAs($bob)
        ->test(Tickets::class)
        ->assertDontSee('Alice secret ticket subject');

    // Deep link 404s.
    $this->actingAs($bob)->get('/support?ticket='.$ticket->id)->assertNotFound();

    // Selecting it directly throws a 404 model lookup.
    expect(fn () => Livewire::actingAs($bob)->test(Tickets::class)->call('select', $ticket->id))
        ->toThrow(ModelNotFoundException::class);

    // Replying into it is impossible too.
    expect(fn () => Livewire::actingAs($bob)
        ->test(Tickets::class)
        ->set('selectedId', $ticket->id)
        ->set('replyBody', 'Trying to sneak into another thread here.')
        ->call('reply'))
        ->toThrow(ModelNotFoundException::class);

    expect($ticket->refresh()->replies)->toHaveCount(1);
});

// ── Admin queue ─────────────────────────────────────────────────────────

test('admin queue tabs filter by status and show counts', function () {
    $admin = ticketsAdmin();
    $user = User::factory()->create();

    makeTicket($user, 'Open ticket one');
    makeTicket($user, 'Open ticket two');
    makeTicket($user, 'Answered ticket', TicketStatus::Answered);
    makeTicket($user, 'Closed ticket', TicketStatus::Closed);

    Livewire::actingAs($admin)
        ->test(AdminTickets::class)
        ->assertViewHas('counts', fn (array $counts) => $counts === ['open' => 2, 'answered' => 1, 'closed' => 1])
        ->assertSee('Open ticket one')
        ->assertSee('Open ticket two')
        ->assertDontSee('Answered ticket')
        ->call('setTab', 'answered')
        ->assertSee('Answered ticket')
        ->assertDontSee('Open ticket one')
        ->call('setTab', 'closed')
        ->assertSee('Closed ticket');
});

test('admin closes, reopens, and toggles priority', function () {
    $admin = ticketsAdmin();
    $ticket = makeTicket(User::factory()->create());

    $component = Livewire::actingAs($admin)->test(AdminTickets::class);

    $component->call('close', $ticket->id);
    expect($ticket->refresh()->status)->toBe(TicketStatus::Closed);

    $component->call('reopen', $ticket->id);
    expect($ticket->refresh()->status)->toBe(TicketStatus::Open);

    $component->call('togglePriority', $ticket->id);
    expect($ticket->refresh()->priority)->toBe(TicketPriority::Urgent);

    $component->call('togglePriority', $ticket->id);
    expect($ticket->refresh()->priority)->toBe(TicketPriority::Normal);
});

// ── Access control ──────────────────────────────────────────────────────

test('non-admins get 403 on the admin ticket queue', function () {
    $this->seed(RoleSeeder::class);
    $buyer = User::factory()->create();

    $this->actingAs($buyer)->get(route('admin.support.tickets'))->assertForbidden();
});

test('admins can open the ticket queue', function () {
    $this->actingAs(ticketsAdmin())->get(route('admin.support.tickets'))->assertOk();
});
