<?php

use App\Enums\DocumentStatus;
use App\Enums\StoreStatus;
use App\Livewire\Seller\ApplicationStatus;
use App\Livewire\Seller\Apply;
use App\Models\Store;
use App\Models\StoreDocument;
use App\Models\User;
use App\Notifications\SellerApplicationReceived;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('public');
});

function makeBuyer(): User
{
    $user = User::factory()->create();
    $user->assignRole('buyer');

    return $user;
}

test('a buyer can submit a seller application', function () {
    Notification::fake();

    $user = makeBuyer();

    Livewire::actingAs($user)
        ->test(Apply::class)
        ->set('name', 'Kedai Pak Ali')
        ->set('description', 'Homemade sambal and kuih, made fresh daily in Shah Alam.')
        ->set('state', 'Selangor')
        ->set('sstRegistered', true)
        ->set('sstNumber', 'W10-1808-32000123')
        ->set('bankName', 'Maybank')
        ->set('accountName', 'Ali bin Abu')
        ->set('accountNumber', '1234567890')
        ->set('ssmFile', UploadedFile::fake()->create('ssm-cert.pdf', 200, 'application/pdf'))
        ->set('icFile', UploadedFile::fake()->image('ic-copy.jpg'))
        ->set('confirm', true)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('seller.status'));

    $store = Store::where('user_id', $user->id)->first();

    expect($store)->not->toBeNull()
        ->and($store->status)->toBe(StoreStatus::Pending)
        ->and($store->slug)->toBe('kedai-pak-ali')
        ->and($store->state)->toBe('Selangor')
        ->and($store->sst_registered)->toBeTrue()
        ->and($store->bank_details)->toBe([
            'bank_name' => 'Maybank',
            'account_name' => 'Ali bin Abu',
            'account_number' => '1234567890',
        ]);

    expect($store->documents()->pluck('type')->sort()->values()->all())->toBe(['ic', 'ssm']);

    $store->documents->each(function (StoreDocument $document) {
        expect($document->status)->toBe(DocumentStatus::Pending)
            ->and($document->getFirstMedia('file'))->not->toBeNull();
    });

    Notification::assertSentTo($user, SellerApplicationReceived::class);
});

test('the application requires documents, bank details and the confirmation', function () {
    Livewire::actingAs(makeBuyer())
        ->test(Apply::class)
        ->set('name', 'Kedai Pak Ali')
        ->call('submit')
        ->assertHasErrors([
            'description' => 'required',
            'state' => 'required',
            'bankName' => 'required',
            'accountName' => 'required',
            'accountNumber' => 'required',
            'ssmFile' => 'required',
            'icFile' => 'required',
            'confirm' => 'accepted',
        ]);

    expect(Store::count())->toBe(0);
});

test('a shop name whose slug is already taken is rejected', function () {
    Store::factory()->create(['name' => 'Kedai Pak Ali']);

    Livewire::actingAs(makeBuyer())
        ->test(Apply::class)
        ->set('name', 'Kedai Pak Ali')
        ->set('description', 'A second shop with the same name.')
        ->set('state', 'Selangor')
        ->set('bankName', 'CIMB')
        ->set('accountName', 'Siti binti Salleh')
        ->set('accountNumber', '99887766554')
        ->set('ssmFile', UploadedFile::fake()->create('ssm.pdf', 100, 'application/pdf'))
        ->set('icFile', UploadedFile::fake()->image('ic.png'))
        ->set('confirm', true)
        ->call('submit')
        ->assertHasErrors(['name']);
});

test('a user who already has a store is redirected away from the apply form', function () {
    $user = makeBuyer();
    Store::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('seller.apply'))
        ->assertRedirect(route('seller.status'));
});

test('an approved seller is redirected from apply and status to the dashboard', function () {
    $user = makeBuyer();
    $user->assignRole('seller');
    Store::factory()->approved()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(route('seller.apply'))->assertRedirect(route('seller.dashboard'));
    $this->actingAs($user)->get(route('seller.status'))->assertRedirect(route('seller.dashboard'));
});

test('a user with a pending store visiting the seller centre is redirected to the status screen', function () {
    $user = makeBuyer();
    Store::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get('/seller')->assertRedirect(route('seller.status'));
});

test('a user without a store visiting the seller centre is redirected to the apply form', function () {
    $this->actingAs(makeBuyer())->get('/seller')->assertRedirect(route('seller.apply'));
});

test('the status screen shows the pending copy', function () {
    $user = makeBuyer();
    Store::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('seller.status'))
        ->assertOk()
        ->assertSee('Application received')
        ->assertSee('2–3 business days');
});

test('a rejected application shows the rejection reason and allows re-applying', function () {
    $user = makeBuyer();
    $store = Store::factory()->create([
        'user_id' => $user->id,
        'status' => StoreStatus::Rejected,
        'rejection_reason' => 'SSM certificate was unreadable.',
    ]);
    StoreDocument::create(['store_id' => $store->id, 'type' => 'ssm', 'status' => DocumentStatus::Rejected]);

    $this->actingAs($user)
        ->get(route('seller.status'))
        ->assertOk()
        ->assertSee('Application rejected')
        ->assertSee('SSM certificate was unreadable.')
        ->assertSee('Re-apply');

    Livewire::actingAs($user)
        ->test(ApplicationStatus::class)
        ->call('reapply')
        ->assertRedirect(route('seller.apply'));

    expect(Store::withTrashed()->find($store->id))->toBeNull()
        ->and(StoreDocument::count())->toBe(0);

    // The form is reachable again and a fresh application can be made.
    // (fresh() — the test's User instance still has the deleted store relation cached.)
    $this->actingAs($user->fresh())->get(route('seller.apply'))->assertOk();
});

test('re-apply is forbidden unless the store is rejected', function () {
    $user = makeBuyer();
    Store::factory()->create(['user_id' => $user->id]); // pending

    Livewire::actingAs($user)
        ->test(ApplicationStatus::class)
        ->call('reapply')
        ->assertStatus(403);
});

test('a suspended store shows the suspension screen', function () {
    $user = makeBuyer();
    $user->assignRole('seller');
    Store::factory()->create([
        'user_id' => $user->id,
        'status' => StoreStatus::Suspended,
        'rejection_reason' => 'Repeated counterfeit listings.',
    ]);

    $this->actingAs($user)->get('/seller')->assertRedirect(route('seller.status'));

    $this->actingAs($user)
        ->get(route('seller.status'))
        ->assertOk()
        ->assertSee('Your shop is suspended')
        ->assertSee('Repeated counterfeit listings.');
});
