<?php

namespace App\Livewire\Admin\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin subscribe-and-save oversight (M2.8): active/paused/cancelled mix and the
 * roster, so support can see replenishment health at a glance.
 */
#[Layout('layouts.admin')]
class Index extends Component
{
    use WithPagination;

    public const PER_PAGE = 20;

    #[Url(except: 'active')]
    public string $tab = 'active';

    public function mount(): void
    {
        if (SubscriptionStatus::tryFrom($this->tab) === null) {
            $this->tab = SubscriptionStatus::Active->value;
        }
    }

    public function updatedTab(): void
    {
        if (SubscriptionStatus::tryFrom($this->tab) === null) {
            $this->tab = SubscriptionStatus::Active->value;
        }

        $this->resetPage();
    }

    public function render(): View
    {
        $counts = Subscription::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('livewire.admin.subscriptions.index', [
            'subscriptions' => Subscription::query()
                ->with(['user', 'variant.product'])
                ->where('status', $this->tab)
                ->latest('id')
                ->paginate(self::PER_PAGE),
            'counts' => collect(SubscriptionStatus::cases())
                ->mapWithKeys(fn (SubscriptionStatus $s) => [$s->value => (int) ($counts[$s->value] ?? 0)])
                ->all(),
        ])->title(__('Subscriptions'));
    }
}
