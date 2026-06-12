<?php

namespace App\Livewire\Admin\Buyers;

use App\Enums\PaymentStatus;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Buyer detail (docs/08 §C) — profile, orders summary, addresses, and
 * suspend/unsuspend with reason. Suspended users are blocked at login by
 * the existing status check. PDPA anonymization arrives in M8 (docs/09 §F).
 */
#[Layout('layouts.admin')]
class Detail extends Component
{
    public User $user;

    public string $suspendReason = '';

    public function mount(User $user): void
    {
        // Admin staff are managed under System → Staff & roles, not here.
        abort_if($user->hasRole('admin'), 404);

        $this->user = $user;
    }

    public function suspend(): void
    {
        $this->validate([
            'suspendReason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'suspendReason.required' => __('Give a reason — it is kept in the audit log.'),
            'suspendReason.min' => __('Give a reason — it is kept in the audit log.'),
        ]);

        $this->user->update(['status' => 'suspended']);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($this->user)
            ->withProperties(['reason' => $this->suspendReason])
            ->log('buyer suspended');

        $this->suspendReason = '';
        $this->dispatch('toast', message: __('Account suspended — they can no longer log in.'));
    }

    public function unsuspend(): void
    {
        $this->user->update(['status' => 'active']);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($this->user)
            ->log('buyer unsuspended');

        $this->dispatch('toast', message: __('Account reinstated — they can log in again.'));
    }

    public function render()
    {
        $this->user->loadMissing('addresses');

        return view('livewire.admin.buyers.detail', [
            'ordersCount' => $this->user->orders()->count(),
            'lifetimeSpendSen' => (int) $this->user->orders()
                ->where('payment_status', PaymentStatus::Paid)
                ->sum('grand_total_sen'),
        ])->title($this->user->name);
    }
}
