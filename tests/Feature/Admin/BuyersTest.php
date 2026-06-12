<?php

use App\Livewire\Admin\Buyers\Detail;
use App\Livewire\Admin\Buyers\Index;
use App\Livewire\Storefront\Auth\Login;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function buyersAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

test('guests are redirected and non-admins get 403 on the buyers screens', function () {
    $this->get(route('admin.buyers.index'))->assertRedirect(route('login'));

    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');

    $this->actingAs($buyer)->get(route('admin.buyers.index'))->assertForbidden();
    $this->actingAs($buyer)->get(route('admin.buyers.show', $buyer))->assertForbidden();
});

test('the buyers list shows non-admin accounts and hides admin staff', function () {
    $admin = buyersAdmin();
    $buyer = User::factory()->create(['name' => 'Aminah Buyer']);
    Order::factory()->count(2)->create(['user_id' => $buyer->id]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('Aminah Buyer')
        ->assertSee($buyer->email)
        ->assertDontSee($admin->email);
});

test('buyers can be searched by email and filtered by status', function () {
    $admin = buyersAdmin();
    $active = User::factory()->create(['name' => 'Active Annie']);
    User::factory()->create(['name' => 'Suspended Sam', 'status' => 'suspended']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('search', $active->email)
        ->assertSee('Active Annie')
        ->assertDontSee('Suspended Sam')
        ->set('search', '')
        ->set('status', 'suspended')
        ->assertSee('Suspended Sam')
        ->assertDontSee('Active Annie');
});

test('the buyer detail shows the orders summary, addresses and the PDPA note', function () {
    $admin = buyersAdmin();
    $buyer = User::factory()->create();
    Order::factory()->paid()->create(['user_id' => $buyer->id, 'grand_total_sen' => 12345]);
    Order::factory()->create(['user_id' => $buyer->id, 'grand_total_sen' => 99999]); // unpaid — not in spend
    $buyer->addresses()->create([
        'label' => 'Home',
        'recipient_name' => 'Aminah binti Ali',
        'phone' => '+60123456789',
        'line1' => '12 Jalan Mawar',
        'postcode' => '47500',
        'city' => 'Subang Jaya',
        'state' => 'Selangor',
        'country' => 'MY',
        'is_default' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.buyers.show', $buyer))
        ->assertOk()
        ->assertSee('RM 123.45')
        ->assertSee('12 Jalan Mawar')
        ->assertSee('PDPA anonymization arrives in M8');
});

test('the buyer detail returns 404 for admin staff accounts', function () {
    $admin = buyersAdmin();
    $otherAdmin = buyersAdmin();

    $this->actingAs($admin)
        ->get(route('admin.buyers.show', $otherAdmin))
        ->assertNotFound();
});

test('suspending a buyer requires a reason, stores it in the audit log and can be undone', function () {
    $admin = buyersAdmin();
    $buyer = User::factory()->create();

    $component = Livewire::actingAs($admin)
        ->test(Detail::class, ['user' => $buyer])
        ->call('suspend')
        ->assertHasErrors(['suspendReason' => 'required']);

    expect($buyer->fresh()->isSuspended())->toBeFalse();

    $component
        ->set('suspendReason', 'Chargeback abuse across multiple orders.')
        ->call('suspend')
        ->assertHasNoErrors();

    expect($buyer->fresh()->isSuspended())->toBeTrue();

    $log = Activity::query()
        ->where('subject_type', User::class)
        ->where('subject_id', $buyer->id)
        ->where('description', 'buyer suspended')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->properties['reason'])->toBe('Chargeback abuse across multiple orders.')
        ->and($log->causer_id)->toBe($admin->id);

    $component->call('unsuspend');

    expect($buyer->fresh()->isSuspended())->toBeFalse();
});

test('a suspended buyer is blocked at login', function () {
    $buyer = User::factory()->create(['status' => 'suspended']);

    Livewire::test(Login::class)
        ->set('email', $buyer->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors(['email']);

    $this->assertGuest();
});
