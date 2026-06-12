<?php

namespace App\Models;

use App\Enums\HelpCategory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class HelpArticle extends Model
{
    use HasFactory, HasTranslations;

    public array $translatable = ['title', 'body'];

    protected $fillable = ['category', 'title', 'body', 'position', 'is_active', 'views'];

    protected function casts(): array
    {
        return [
            'category' => HelpCategory::class,
            'position' => 'integer',
            'is_active' => 'boolean',
            'views' => 'integer',
        ];
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
