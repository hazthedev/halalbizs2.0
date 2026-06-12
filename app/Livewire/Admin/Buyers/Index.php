<?php

namespace App\Livewire\Admin\Buyers;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Buyers management (docs/08 §C) — every non-admin account with search and
 * an active/suspended filter. Rows open the buyer detail page.
 */
#[Layout('layouts.admin')]
class Index extends Component
{
    use WithPagination;

    public const PER_PAGE = 20;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $buyers = User::query()
            ->whereDoesntHave('roles', fn (Builder $query) => $query->where('name', 'admin'))
            ->when(in_array($this->status, ['active', 'suspended'], true), fn (Builder $query) => $query->where('status', $this->status))
            ->when(trim($this->search) !== '', function (Builder $query) {
                $term = '%'.trim($this->search).'%';

                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term));
            })
            ->withCount('orders')
            ->latest('created_at')
            ->latest('id')
            ->paginate(self::PER_PAGE);

        return view('livewire.admin.buyers.index', [
            'buyers' => $buyers,
        ])->title(__('Buyers'));
    }
}
