<?php

use App\Livewire\Storefront\Auth\ForgotPassword;
use App\Livewire\Storefront\Auth\Login;
use App\Livewire\Storefront\Auth\Register;
use App\Livewire\Storefront\Auth\ResetPassword;
use App\Livewire\Storefront\Auth\VerifyEmailNotice;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('auth pages render', function (string $route) {
    $this->get(route($route))->assertOk();
})->with(['login', 'register', 'password.request']);

test('register creates a buyer and redirects to the verification notice', function () {
    Notification::fake();

    Livewire::test(Register::class)
        ->set('name', 'Aisha binti Ali')
        ->set('email', 'aisha@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('phone', '012-345 6789')
        ->set('terms', true)
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('verification.notice'));

    $user = User::where('email', 'aisha@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole('buyer'))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeFalse();

    $this->assertAuthenticatedAs($user);

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('register requires ToS consent', function () {
    Livewire::test(Register::class)
        ->set('name', 'Aisha binti Ali')
        ->set('email', 'aisha@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('terms', false)
        ->call('register')
        ->assertHasErrors(['terms']);

    $this->assertGuest();
});

test('login authenticates and redirects home', function () {
    $user = User::factory()->create();

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

test('suspended users cannot log in', function () {
    $user = User::factory()->create(['status' => 'suspended']);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors(['email'])
        ->assertSee('Your account has been suspended.');

    $this->assertGuest();
});

test('login is rate limited after 5 attempts', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors(['email']);
    }

    // 6th attempt is blocked even with the correct password.
    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors(['email'])
        ->assertSee('Too many login attempts')
        ->assertSee('seconds');

    $this->assertGuest();
});

test('forgot password always shows the generic status', function () {
    $user = User::factory()->create();

    Livewire::test(ForgotPassword::class)
        ->set('email', $user->email)
        ->call('sendResetLink')
        ->assertSee('If that email exists, a reset link is on the way.');

    Livewire::test(ForgotPassword::class)
        ->set('email', 'nobody@example.com')
        ->call('sendResetLink')
        ->assertSee('If that email exists, a reset link is on the way.');
});

test('reset password updates the password and redirects to login', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', $user->email)
        ->set('password', 'new-password-123')
        ->set('password_confirmation', 'new-password-123')
        ->call('resetPassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
});

test('verification notice resends once per minute', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    Livewire::actingAs($user)
        ->test(VerifyEmailNotice::class)
        ->call('resend')
        ->assertHasNoErrors()
        ->call('resend')
        ->assertHasErrors(['resend']);

    Notification::assertSentToTimes($user, VerifyEmail::class, 1);
});

test('verified users are redirected away from the verification notice', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(VerifyEmailNotice::class)
        ->assertRedirect(route('home'));
});
