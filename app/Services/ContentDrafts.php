<?php

namespace App\Services;

use App\Models\Banner;
use App\Models\ContentVersion;
use App\Models\HelpArticle;
use App\Models\HomeSection;
use App\Models\Page;
use App\Models\ThemeAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The ONE place content goes from "written" to "live". An admin screen never
 * saves a banner, page, help article, home section or the theme directly: it
 * stage()s the change as a draft. Shoppers keep seeing the live row until a
 * publisher publish()es it. Staff previewing see the draft through preview()
 * and visible(). Every publish is kept as a version (the last KEEP per item).
 */
class ContentDrafts
{
    /** Published versions kept per item. */
    public const KEEP = 20;

    /** Short names the admin screens use for the content types. */
    public const SUBJECTS = [
        'banner' => Banner::class,
        'page' => Page::class,
        'section' => HomeSection::class,
        'article' => HelpArticle::class,
        'theme' => ThemeAsset::class,
    ];

    /** Payload key: live file collections to empty at publish. Keys starting with "_" are not model values. */
    public const CLEAR = '_clear_media';

    /** True while a staff member is looking at a preview (set by the PreviewDrafts middleware). */
    public bool $previewing = false;

    /**
     * Save the values now on $subject as its draft; the live row is not changed.
     * A brand-new item is stored hidden first, so the draft has something to point at.
     * A draft equal to the live values, with no files, is dropped (returns null).
     *
     * Files: add an uploaded picture or video to the returned draft
     * (`$draft->addMedia(...)->toMediaCollection('image')`), and name live
     * files to remove at publish in $clearMedia. Pass $withFiles so a draft
     * that changes only files is kept.
     *
     * @param  array<string, mixed>|null  $payload  for subjects whose values do not live on the model (theme)
     * @param  array<int, string>  $clearMedia  collections of the live item to empty at publish
     */
    public function stage(Model $subject, ?User $by = null, ?array $payload = null, bool $withFiles = false, array $clearMedia = []): ?ContentVersion
    {
        return DB::transaction(function () use ($subject, $by, $payload, $withFiles, $clearMedia) {
            $payload ??= $subject->draftState();

            if (! $subject->exists) {
                $subject->is_active = false;
                $subject->save();
            }

            $live = $subject->fresh();
            $liveState = $live->draftState();
            $draft = $live->draft()->first();
            $clearMedia = array_values(array_unique([...($draft?->payload[self::CLEAR] ?? []), ...$clearMedia]));
            $hasFiles = $withFiles || $clearMedia !== [] || ($draft !== null && $draft->media()->exists());

            if (! $hasFiles && $this->same($payload, $liveState)) {
                $draft?->delete();

                return null;
            }

            // First change to an item that was live before drafts existed: keep what was live, so it can be restored.
            if (! $subject->wasRecentlyCreated && ! $live->versions()->exists()) {
                $this->record($live, 'published', $liveState, null, published: true);
            }

            if ($clearMedia !== []) {
                $payload[self::CLEAR] = $clearMedia;
            }

            // The same row is kept across saves, so files already uploaded to the draft stay with it.
            if ($draft !== null) {
                $draft->update(['payload' => $payload, 'user_id' => $by?->id ?? $draft->user_id]);

                return $draft;
            }

            return $this->record($live, 'draft', $payload, $by);
        });
    }

    /** Make the draft live and keep it as a version. No draft = nothing happens. */
    public function publish(Model $subject, User $by): void
    {
        DB::transaction(function () use ($subject, $by) {
            $draft = $subject->draft()->first();

            if ($draft === null) {
                return;
            }

            $live = $subject->fresh();
            $live->fillFromDraft($this->values($draft->payload));
            $live->saveAsLive();

            foreach ($draft->payload[self::CLEAR] ?? [] as $collection) {
                $live->clearMediaCollection($collection);
            }

            foreach ($draft->media as $media) {
                $media->move($live, $media->collection_name);
            }

            $draft->update(['status' => 'published', 'published_by' => $by->id, 'published_at' => now()]);

            $subject->versions()->skip(self::KEEP)->take(PHP_INT_MAX)->get()->each->delete();
        });
    }

    public function discard(Model $subject): void
    {
        // Through the model, so the draft's uploaded files are removed with it.
        $subject->draft()->first()?->delete();
    }

    /**
     * The item as it would look once its draft is published: values and
     * files. Unsaved; the same item when it has no draft.
     */
    public function preview(Model $subject): Model
    {
        $draft = $subject->draft()->first();

        if ($draft === null) {
            return $subject;
        }

        $subject->fillFromDraft($this->values($draft->payload));

        $cleared = $draft->payload[self::CLEAR] ?? [];

        if ($cleared !== [] || $draft->media->isNotEmpty()) {
            $replaced = [...$cleared, ...$draft->media->pluck('collection_name')->all()];

            $subject->setRelation('media', $subject->media
                ->reject(fn ($media) => in_array($media->collection_name, $replaced, true))
                ->concat($draft->media)
                ->values());
        }

        return $subject;
    }

    /**
     * Every item of a type a visitor should see: the live ones for shoppers,
     * the drafted ones for staff in a preview. Ordered by position where the type has one.
     *
     * @param  class-string<Model>  $class
     * @param  array<int, string>  $with
     * @return Collection<int, Model>
     */
    public function visible(string $class, array $with = []): Collection
    {
        if (! $this->previewing) {
            return $class::active()->with($with)->get();
        }

        return $class::query()->with($with)->get()
            ->map(fn (Model $item) => $this->preview($item))
            ->filter(fn (Model $item) => $item->isActiveNow())
            ->sortBy(fn (Model $item) => [(int) ($item->position ?? 0), $item->id])
            ->values();
    }

    /** One item of a type by a column, as visible() would show it; null when hidden. */
    public function find(string $class, string $column, mixed $value): ?Model
    {
        if (! $this->previewing) {
            return $class::active()->where($column, $value)->first();
        }

        $item = $class::query()->where($column, $value)->first();
        $item = $item === null ? null : $this->preview($item);

        return $item?->isActiveNow() ? $item : null;
    }

    /** A payload without its "_" bookkeeping keys. */
    private function values(array $payload): array
    {
        return array_filter($payload, fn (string $key) => ! str_starts_with($key, '_'), ARRAY_FILTER_USE_KEY);
    }

    private function record(Model $subject, string $status, array $payload, ?User $by, bool $published = false): ContentVersion
    {
        return ContentVersion::create([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'status' => $status,
            'payload' => $payload,
            'user_id' => $by?->id,
            'published_at' => $published ? now() : null,
        ]);
    }

    /** Stored values compare loosely: "1" and true, and JSON text in a different key order, are the same. */
    private function same(array $a, array $b): bool
    {
        $norm = function (array $state): array {
            ksort($state);

            return array_map(function ($value) {
                $decoded = is_string($value) ? json_decode($value, true) : null;

                if (is_array($decoded)) {
                    ksort($decoded);

                    return $decoded;
                }

                return is_bool($value) ? (int) $value : (is_numeric($value) ? $value + 0 : $value);
            }, $state);
        };

        return $norm($a) == $norm($b);
    }
}
