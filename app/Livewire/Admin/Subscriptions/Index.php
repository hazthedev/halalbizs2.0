<?php

namespace App\Livewire\Admin\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use App\Livewire\Concerns\WithTable;
use App\Support\JsonSearch;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Admin subscribe-and-save oversight (M2.8): active/paused/cancelled mix and the
 * roster, so support can see replenishment health at a glance.
 */
#[Layout('layouts.admin')]
class Index extends Component
{
    use WithTable;

    public const PER_PAGE = 20;

    #[Url(except: 'active')]
    public string $tab = 'active';

    protected function tableFilters(): array
    {
        return ['tab'];
    }

    protected function tableSorts(): array
    {
        return ['every' => 'interval_days', 'next' => 'next_run_at'];
    }

    protected function tableDefaultOrder(): array
    {
        return ['id' => 'desc'];
    }

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
    }

    public function render(): View
    {
        $counts = Subscription::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('livewire.admin.subscriptions.index', [
            'subscriptions' => $this->results(),
            'counts' => collect(SubscriptionStatus::cases())
                ->mapWithKeys(fn (SubscriptionStatus $s) => [$s->value => (int) ($counts[$s->value] ?? 0)])
                ->all(),
        ])->title(__('Subscriptions'));
    }

    private function results()
    {
        $term = JsonSearch::pattern($this->search);

        return $this->sortTable(Subscription::query()
            ->with(['user', 'variant.product'])
            ->where('status', $this->tab)
            ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($q) => $q
                ->whereHas('user', fn ($u) => $u->whereRaw(JsonSearch::clause('name'), [$term])->orWhereRaw(JsonSearch::clause('email'), [$term]))
                ->orWhereHas('variant.product', fn ($p) => $p->whereRaw(JsonSearch::clause('name->en'), [$term])))))
            ->paginate($this->tablePageSize());
    }
}
