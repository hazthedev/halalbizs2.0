<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Singleton media anchor for seasonal theming (settings can't hold uploads).
 * One row per key — currently only 'hero'.
 */
class ThemeAsset extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['key'];

    public static function hero(): self
    {
        return static::firstOrCreate(['key' => 'hero']);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('card')->width(1600)->performOnCollections('image');
    }
}
