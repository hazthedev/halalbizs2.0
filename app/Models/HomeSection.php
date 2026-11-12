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

class HomeSection extends Model implements HasMedia
{
    use HasDrafts;
    use HasFactory, HasTranslations, InteractsWithMedia, LogsContentChanges;

    /** A picture_tiles section holds 2 to 4 tiles; tile N keeps its picture in collection "tile_N". */
    public const TILE_SLOTS = [1, 2, 3, 4];

    /** @return array<int, string> */
    public static function tileCollections(): array
    {
        return array_map(fn (int $slot) => 'tile_'.$slot, self::TILE_SLOTS);
    }

    public function registerMediaCollections(): void
    {
        foreach (self::tileCollections() as $collection) {
            $this->addMediaCollection($collection)->singleFile();
        }
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('tile')->width(800)->performOnCollections(...self::tileCollections());
    }

    public array $translatable = ['title'];

    protected $fillable = ['type', 'title', 'payload', 'position', 'is_active', 'show_on'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'is_active' => 'boolean',
            // Not fillable on purpose: a draft can never move a section to another layout.
            'scheduled_layout_id' => 'integer',
        ];
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('position');
    }
}
