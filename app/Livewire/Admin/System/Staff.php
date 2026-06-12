<?php

namespace App\Livewire\Admin\System;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Staff & roles (docs/08 §I) — admins carry the `admin` role plus a DIRECT
 * permission subset (route groups gate on can:* per section). Inviting
 * generates a temporary password shown exactly once.
 */
#[Layout('layouts.admin')]
class Staff extends Component
{
    // ── Invite ─────────────────────────────────────────────────────────
    public bool $showInvite = false;

    public string $inviteName = '';

    public string $inviteEmail = '';

    /** @var array<int, string> */
    public array $invitePermissions = [];

    /** Shown once after a successful invite. */
    public ?string $generatedPassword = null;

    public ?string $generatedFor = null;

    // ── Edit permissions ───────────────────────────────────────────────
    #[Locked]
    public ?int $editingId = null;

    /** @var array<int, string> */
    public array $editPermissions = [];

    public function startInvite(): void
    {
        $this->reset(['showInvite', 'inviteName', 'inviteEmail', 'invitePermissions', 'generatedPassword', 'generatedFor']);
        $this->resetErrorBag();
        $this->showInvite = true;
    }

    public function cancelInvite(): void
    {
        $this->reset(['showInvite', 'inviteName', 'inviteEmail', 'invitePermissions']);
        $this->resetErrorBag();
    }

    public function invite(): void
    {
        $this->validate([
            'inviteName' => ['required', 'string', 'max:120'],
            'inviteEmail' => ['required', 'email', 'unique:users,email'],
            'invitePermissions' => ['array'],
            'invitePermissions.*' => ['string', 'in:'.implode(',', RoleSeeder::ADMIN_PERMISSIONS)],
        ], attributes: [
            'inviteName' => __('name'),
            'inviteEmail' => __('email'),
        ]);

        $password = Str::password(16);

        $user = User::create([
            'name' => trim($this->inviteName),
            'email' => strtolower(trim($this->inviteEmail)),
            'password' => $password,
        ]);
        $user->markEmailAsVerified();

        $user->assignRole('admin');
        $user->syncPermissions(array_values(array_intersect($this->invitePermissions, RoleSeeder::ADMIN_PERMISSIONS)));

        $this->generatedPassword = $password;
        $this->generatedFor = $user->email;
        $this->reset(['showInvite', 'inviteName', 'inviteEmail', 'invitePermissions']);

        $this->dispatch('toast', message: __('Admin invited — share the temporary password securely.'));
    }

    public function dismissPassword(): void
    {
        $this->reset(['generatedPassword', 'generatedFor']);
    }

    public function editPermissions(int $userId): void
    {
        $user = User::role('admin')->findOrFail($userId);

        $this->resetErrorBag();
        $this->editingId = $user->id;
        $this->editPermissions = $user->getDirectPermissions()->pluck('name')->all();
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingId', 'editPermissions']);
        $this->resetErrorBag();
    }

    public function savePermissions(): void
    {
        $this->validate([
            'editPermissions' => ['array'],
            'editPermissions.*' => ['string', 'in:'.implode(',', RoleSeeder::ADMIN_PERMISSIONS)],
        ]);

        $user = User::role('admin')->findOrFail($this->editingId);
        $user->syncPermissions(array_values(array_intersect($this->editPermissions, RoleSeeder::ADMIN_PERMISSIONS)));

        $this->dispatch('toast', message: __('Permissions updated for :name', ['name' => $user->name]));
        $this->cancelEdit();
    }

    public function removeAdmin(int $userId): void
    {
        // Never let an admin lock themselves out.
        if ($userId === auth()->id()) {
            $this->dispatch('toast', message: __('You can\'t remove your own admin access.'), type: 'error');

            return;
        }

        $user = User::role('admin')->findOrFail($userId);
        $user->removeRole('admin');
        $user->syncPermissions([]);

        if ($this->editingId === $userId) {
            $this->cancelEdit();
        }

        $this->dispatch('toast', message: __('Admin access removed for :name', ['name' => $user->name]));
    }

    public function render()
    {
        return view('livewire.admin.system.staff', [
            'admins' => User::role('admin')->with('permissions')->orderBy('name')->get(),
            'allPermissions' => RoleSeeder::ADMIN_PERMISSIONS,
        ])->title(__('Staff & roles'));
    }
}
