<?php

namespace App\Livewire\Admin\Buyers;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Livewire\Concerns\WithTable;

/**
 * Buyers management (docs/08 §C) — every non-admin account with search and
 * an active/suspended filter. Rows open the buyer detail page.
 */
#[Layout('layouts.admin')]
class Index extends Component
{
    use WithTable;

    public const PER_PAGE = 20;

    #[Url(except: '')]
    public string $status = '';

    protected function tableFilters(): array
    {
        return ['status'];
    }

    protected function tableSorts(): array
    {
        return ['name' => 'name', 'email' => 'email', 'orders' => 'orders_count', 'joined' => 'created_at', 'status' => 'status'];
    }

    protected function tableDefaultOrder(): array
    {
        return ['created_at' => 'desc'];
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status');
        $this->resetPage();
    }

    public function render()
    {
        $buyers = User::query()
            ->whereDoesntHave('roles', fn (Builder $query) => $query->where('name', 'admin'))
            ->when(in_array($this->status, ['active', 'suspended'], true), fn (Builder $query) => $query->where('status', $this->status))
            ->when(trim($this->search) !== '', function (Builder $query) {
                $term = $this->likeTerm();

                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term));
            })
            ->withCount('orders')
        ;

        return view('livewire.admin.buyers.index', [
            'buyers' => $this->sortTable($buyers)->paginate($this->tablePageSize()),
        ])->title(__('Buyers'));
    }
}
