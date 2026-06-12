<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

class Banner extends Model implements HasMedia
{
    use HasFactory, HasTranslations, InteractsWithMedia;

    public array $translatable = ['title'];

    protected $fillable = ['title', 'link_url', 'position', 'starts_at', 'ends_at', 'is_active'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();

        // Optional motion slide (mp4/webm, ≤30MB — enforced by the admin
        // form's mimetypes/max validation). The image stays as the fallback.
        $this->addMediaCollection('video')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('card')->width(1600)->performOnCollections('image');
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('position');
    }
}
