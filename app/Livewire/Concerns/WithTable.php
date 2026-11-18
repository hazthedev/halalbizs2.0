<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

trait WithTable
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $sort = '';

    #[Url(except: 'asc')]
    public string $direction = 'asc';

    #[Url]
    public int $perPage = 20;

    public array $selected = [];

    public bool $selectPage = false;

    public function initializeWithTable(): void
    {
        $this->perPage = self::PER_PAGE;
    }

    public function updatedWithTable(string $property): void
    {
        if (in_array($property, ['search', 'sort', 'direction', 'perPage', ...$this->tableFilters()], true)) {
            $this->resetPage();
            $this->clearSelection();
        }
    }

    public function updatedPaginators(): void
    {
        $this->clearSelection();
    }

    public function updatedSelectPage(bool $value): void
    {
        $this->selected = $value
            ? $this->results()->pluck('id')->map(fn ($id) => (string) $id)->all()
            : [];
    }

    public function sortBy(string $field): void
    {
        if (! array_key_exists($field, $this->tableSorts())) {
            return;
        }

        $this->direction = $this->sort === $field && $this->direction === 'asc' ? 'desc' : 'asc';
        $this->sort = $field;
        $this->resetPage();
        $this->clearSelection();
    }

    protected function sortTable(Builder $query): Builder
    {
        $column = $this->tableSorts()[$this->sort] ?? null;

        if ($column !== null) {
            $query->orderBy($column, $this->direction === 'desc' ? 'desc' : 'asc');
        } else {
            foreach ($this->tableDefaultOrder() as $field => $direction) {
                $query->orderBy($field, $direction);
            }
        }

        return $query->orderBy($query->getModel()->qualifyColumn('id'), 'desc');
    }

    protected function tablePageSize(): int
    {
        return in_array($this->perPage, [15, 20, 50, 100], true) ? $this->perPage : self::PER_PAGE;
    }

    protected function clearSelection(): void
    {
        $this->selected = [];
        $this->selectPage = false;
    }
}
