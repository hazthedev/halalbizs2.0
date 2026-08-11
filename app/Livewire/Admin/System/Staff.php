<?php

namespace App\Livewire\Admin\System;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Password;
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

    // ── Edit permissions ───────────────────────────────────────────────
    #[Locked]
    public ?int $editingId = null;

    /** @var array<int, string> */
    public array $editPermissions = [];

    public function startInvite(): void
    {
        $this->reset(['showInvite', 'inviteName', 'inviteEmail', 'invitePermissions']);
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

        $requested = array_values(array_intersect($this->invitePermissions, RoleSeeder::ADMIN_PERMISSIONS));

        // Same rule savePermissions() enforces, applied to the CREATE path: an
        // admin must not end up controlling an account with more access than
        // their own, or a settings.manage admin simply invites themselves a
        // finance.manage second account. Only a superadmin (the intended
        // escalation path, see toggleSuperadmin()) may grant beyond their own
        // grants. Fail closed — an over-reaching request grants NOTHING rather
        // than being silently trimmed, which would read as a clean success.
        $inviter = auth()->user();
        $overReach = $inviter->is_superadmin
            ? []
            : array_diff($requested, $inviter->getDirectPermissions()->pluck('name')->all());

        $user = User::create([
            'name' => trim($this->inviteName),
            'email' => strtolower(trim($this->inviteEmail)),
            'password' => Str::password(32),
        ]);
        $user->markEmailAsVerified();

        $user->assignRole('admin');
        $user->syncPermissions($overReach === [] ? $requested : []);

        // The random password above is known to nobody: the invitee claims the
        // account through the standard reset link. Showing the inviter a working
        // plaintext login for another admin was the other half of the hole.
        Password::sendResetLink(['email' => $user->email]);

        $this->reset(['showInvite', 'inviteName', 'inviteEmail', 'invitePermissions']);

        $this->dispatch(
            'toast',
            message: $overReach === []
                ? __('Admin invited — they will receive an email to set their password.')
                : __('Admin invited with no permissions — you can only grant permissions you hold yourself.'),
            type: $overReach === [] ? 'success' : 'error',
        );
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
