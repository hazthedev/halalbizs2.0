<?php

namespace App\Models;

use App\Models\Concerns\HasDrafts;
use App\Models\Concerns\LogsContentChanges;
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
    use HasDrafts;
    use HasFactory, HasTranslations, InteractsWithMedia, LogsContentChanges;

    public array $translatable = ['title', 'subtitle', 'cta_label'];

    protected $fillable = ['title', 'subtitle', 'cta_label', 'link_url', 'position', 'starts_at', 'ends_at', 'is_active'];

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
        $this->addMediaCollection('image_vi')->singleFile();

        // Optional motion slide (mp4/webm, ≤30MB — enforced by the admin
        // form's mimetypes/max validation). The image stays as the fallback.
        $this->addMediaCollection('video')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('card')->width(1600)->performOnCollections('image', 'image_vi');
        // Phone-size copy in a smaller format; the home page offers it through srcset.
        $this->addMediaConversion('small')->width(800)->format('webp')->performOnCollections('image', 'image_vi');
    }

    /** Locale artwork with the base image as a deliberate fallback. */
    public function imageUrl(string $locale, string $conversion = 'card'): string
    {
        if ($locale === 'vi') {
            $localized = $this->getFirstMediaUrl('image_vi', $conversion);

            if ($localized !== '') {
                return $localized;
            }
        }

        return $this->getFirstMediaUrl('image', $conversion);
    }

    /**
     * "small 800w, card 1600w" for the browser to choose from. Empty when the
     * small copy does not exist (banners saved before it, or a server whose
     * image library cannot write WebP), so the plain image keeps working.
     */
    public function imageSrcset(string $locale): string
    {
        $media = ($locale === 'vi' ? $this->getFirstMedia('image_vi') : null) ?? $this->getFirstMedia('image');

        if ($media === null || ! $media->hasGeneratedConversion('small') || ! $media->hasGeneratedConversion('card')) {
            return '';
        }

        return $media->getUrl('small').' 800w, '.$media->getUrl('card').' 1600w';
    }

    /** The same rule as the `active` scope, for a banner that carries an unsaved draft (preview). */
    public function isActiveNow(): bool
    {
        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->lte(now()))
            && ($this->ends_at === null || $this->ends_at->gte(now()));
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
