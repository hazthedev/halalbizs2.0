<?php

namespace App\Models;

use App\Models\Concerns\HasDrafts;
use App\Models\Concerns\LogsContentChanges;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class HomeSection extends Model
{
    use HasDrafts;
    use HasFactory, HasTranslations, LogsContentChanges;

    public array $translatable = ['title'];

    protected $fillable = ['type', 'title', 'payload', 'position', 'is_active', 'show_on'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'is_active' => 'boolean',
        ];
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('position');
    }
}
