<?php

namespace App\Models\Concerns;

use App\Models\ContentVersion;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Arr;

/**
 * Content that is written as a draft and made live by a publisher
 * (App\Services\ContentDrafts owns the rules). A model answers three
 * questions: what a draft carries, how a draft is put onto it, and whether
 * it would show right now.
 */
trait HasDrafts
{
    /** The draft waiting for a publisher, if any. */
    public function draft(): MorphOne
    {
        return $this->morphOne(ContentVersion::class, 'subject')->where('status', 'draft');
    }

    /** What went live, newest first. */
    public function versions(): MorphMany
    {
        return $this->morphMany(ContentVersion::class, 'subject')->where('status', 'published')->latest('id');
    }

    /**
     * The values a draft carries, as stored (translated fields keep every language).
     *
     * @return array<string, mixed>
     */
    public function draftState(): array
    {
        return Arr::only($this->getAttributes(), array_diff($this->getFillable(), ['views']));
    }

    /**
     * Put a draft's values on this model in memory. Nothing is saved.
     *
     * @param  array<string, mixed>  $payload
     */
    public function fillFromDraft(array $payload): void
    {
        $this->setRawAttributes(array_merge($this->getAttributes(), $payload));
    }

    /** Make the values now on this model the live ones. */
    public function saveAsLive(): void
    {
        $this->save();
    }

    /** Would a shopper see this right now? */
    public function isActiveNow(): bool
    {
        return (bool) $this->is_active;
    }
}
