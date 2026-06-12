<?php

namespace App\Livewire\Admin\System;

use App\Models\Payout;
use App\Models\Product;
use App\Models\Store;
use App\Models\SubOrder;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

/**
 * Audit log viewer (docs/08 §I) — read-only activity_log datagrid with an
 * expandable old → new diff per row.
 */
#[Layout('layouts.admin')]
class AuditLog extends Component
{
    use WithPagination;

    public const SUBJECT_TYPES = [
        'Store' => Store::class,
        'Product' => Product::class,
        'SubOrder' => SubOrder::class,
        'Payout' => Payout::class,
    ];

    public string $subjectType = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function updatedSubjectType(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['subjectType', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function render()
    {
        $activities = Activity::query()
            ->with('causer')
            ->when(
                $this->subjectType !== '' && isset(self::SUBJECT_TYPES[$this->subjectType]),
                fn ($query) => $query->where('subject_type', self::SUBJECT_TYPES[$this->subjectType]),
            )
            ->when($this->dateFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $this->dateTo))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25);

        return view('livewire.admin.system.audit-log', [
            'activities' => $activities,
            'subjectTypes' => array_keys(self::SUBJECT_TYPES),
        ])->title(__('Audit log'));
    }
}
