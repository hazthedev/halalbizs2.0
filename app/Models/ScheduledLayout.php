<?php

namespace App\Models;

use App\Models\Concerns\LogsContentChanges;
use App\Services\HomeLayout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named home layout with a start and an end. Between the two the home
 * shows its sections instead of the everyday ones (App\Services\HomeLayout
 * picks by the clock on every read, so nothing has to run at the switch).
 */
class ScheduledLayout extends Model
{
    use LogsContentChanges;

    protected $fillable = ['name', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => HomeLayout::flush());
        static::deleted(fn () => HomeLayout::flush());
        // One by one, so each section's own delete hooks (audit, cache) run.
        static::deleting(fn (self $layout) => $layout->sections()->get()->each->delete());
    }

    public function sections(): HasMany
    {
        return $this->hasMany(HomeSection::class);
    }

    /** The layout showing right now; when two overlap, the one that started last. */
    public static function current(): ?self
    {
        return static::where('starts_at', '<=', now())->where('ends_at', '>', now())
            ->orderByDesc('starts_at')->orderByDesc('id')->first();
    }

    /** Seconds until any layout next starts or ends; null when none is ahead. */
    public static function secondsToNextSwitch(): ?int
    {
        $next = collect([
            static::where('starts_at', '>', now())->min('starts_at'),
            static::where('ends_at', '>', now())->min('ends_at'),
        ])->filter()->min();

        return $next === null ? null : (int) ceil(now()->diffInSeconds($next, absolute: true));
    }

    public function isShowingNow(): bool
    {
        return $this->starts_at->lte(now()) && $this->ends_at->gt(now());
    }
}
