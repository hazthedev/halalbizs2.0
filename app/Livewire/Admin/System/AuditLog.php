<?php

namespace App\Livewire\Admin\System;

use App\Models\Payout;
use App\Models\Product;
use App\Models\Store;
use App\Models\SubOrder;
use App\Support\DisplayTime;
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
            ->when($this->isDay($this->dateFrom), fn ($query) => $query->where('created_at', '>=', DisplayTime::dayStart($this->dateFrom)))
            ->when($this->isDay($this->dateTo), fn ($query) => $query->where('created_at', '<=', DisplayTime::dayEnd($this->dateTo)))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25);

        return view('livewire.admin.system.audit-log', [
            'activities' => $activities,
            'subjectTypes' => array_keys(self::SUBJECT_TYPES),
        ])->title(__('Audit log'));
    }

    private function isDay(string $value): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) !== 1) {
            return false;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }
}
