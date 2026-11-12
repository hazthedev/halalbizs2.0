<?php

namespace App\Models;

use App\Models\Concerns\HasDrafts;
use App\Models\Concerns\LogsContentChanges;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

class Page extends Model implements HasMedia
{
    use HasDrafts;
    use HasFactory, HasTranslations, LogsContentChanges;
    use InteractsWithMedia;

    public array $translatable = ['title', 'body', 'seo_title', 'seo_description'];

    protected $fillable = ['slug', 'title', 'body', 'is_active', 'seo_title', 'seo_description', 'show_in_footer'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'show_in_footer' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        // The picture shown when the page is shared (WhatsApp, Facebook, X).
        $this->addMediaCollection('share_image')->singleFile();
    }

    /** Title for search engines and shares: the admin's own, or the page title. */
    public function seoTitle(string $locale): string
    {
        return $this->getTranslation('seo_title', $locale) ?: $this->getTranslation('title', $locale);
    }

    /** Description for search engines and shares: the admin's own, or the start of the page text. */
    public function seoDescription(string $locale): string
    {
        return $this->getTranslation('seo_description', $locale)
            ?: Str::limit(trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($this->getTranslation('body', $locale))))), 160);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
