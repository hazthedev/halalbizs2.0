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
        // No self-service permission grants: a settings.manage-only admin
        // (the least-privileged person who can even reach this screen)
        // must not be able to sync finance.manage etc. onto their own
        // account. Superadmin status (the intended escalation path) is
        // handled separately by toggleSuperadmin(), which already checks
        // is_superadmin — this mirrors that rigour for direct permissions.
        if ($userId === auth()->id()) {
            $this->dispatch('toast', message: __('You can\'t change your own permissions.'), type: 'error');

            return;
        }

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
        // Defense in depth: editPermissions() already refuses to point
        // editingId at the caller's own id, but never trust that alone.
        if ($this->editingId === auth()->id()) {
            $this->dispatch('toast', message: __('You can\'t change your own permissions.'), type: 'error');
            $this->cancelEdit();

            return;
        }

        $this->validate([
            'editPermissions' => ['array'],
            'editPermissions.*' => ['string', 'in:'.implode(',', RoleSeeder::ADMIN_PERMISSIONS)],
        ]);

        $user = User::role('admin')->findOrFail($this->editingId);
        $user->syncPermissions(array_values(array_intersect($this->editPermissions, RoleSeeder::ADMIN_PERMISSIONS)));

        $this->dispatch('toast', message: __('Permissions updated for :name', ['name' => $user->name]));
        $this->cancelEdit();
    }

    /**
     * Promote/demote a superadmin. A superadmin passes every permission check
     * (Gate::before), so only a superadmin may hand that out — otherwise any
     * admin who reached this screen could grant themselves everything, which is
     * the hole this whole change closes.
     */
    public function toggleSuperadmin(int $userId): void
    {
        if (! auth()->user()->is_superadmin) {
            $this->dispatch('toast', message: __('Only a superadmin can change superadmin access.'), type: 'error');

            return;
        }

        $user = User::role('admin')->findOrFail($userId);

        if ($user->is_superadmin) {
            // Never leave the platform with nobody able to grant permissions:
            // the last superadmin cannot be demoted, not even by themselves.
            if (User::where('is_superadmin', true)->count() <= 1) {
                $this->dispatch('toast', message: __('There must always be at least one superadmin.'), type: 'error');

                return;
            }

            $user->forceFill(['is_superadmin' => false])->save();
            $this->dispatch('toast', message: __('Superadmin access removed for :name', ['name' => $user->name]));

            return;
        }

        $user->forceFill(['is_superadmin' => true])->save();
        $this->dispatch('toast', message: __(':name is now a superadmin.', ['name' => $user->name]));
    }

    public function removeAdmin(int $userId): void
    {
        // Never let an admin lock themselves out.
        if ($userId === auth()->id()) {
            $this->dispatch('toast', message: __('You can\'t remove your own admin access.'), type: 'error');

            return;
        }

        $user = User::role('admin')->findOrFail($userId);

        // Removing the last superadmin's admin role is the same lockout by
        // another door — block it at this end too.
        if ($user->is_superadmin && User::where('is_superadmin', true)->count() <= 1) {
            $this->dispatch('toast', message: __('There must always be at least one superadmin.'), type: 'error');

            return;
        }

        $user->removeRole('admin');
        $user->syncPermissions([]);
        $user->forceFill(['is_superadmin' => false])->save();

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
