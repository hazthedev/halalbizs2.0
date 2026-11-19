<div class="space-y-4">

    <x-ui.section-heading :title="__('Help articles')" as="h1">
        <x-slot:actions>
            <x-ui.button icon="plus" :href="route('admin.support.articles.create')">
                {{ __('Add article') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.section-heading>

    @if (session('status'))
        <div role="status" class="rounded-[var(--radius-control)] bg-emerald-tint px-4 py-3 text-sm text-emerald-deep">{{ session('status') }}</div>
    @endif

    <x-ui.table.search :placeholder="__('Search title')">
        <label for="articles-category" class="sr-only">{{ __('Category') }}</label>
        <select id="articles-category" wire:model.live="category"
                class="block min-h-11 rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
            <option value="">{{ __('All categories') }}</option>
            @foreach ($categories as $categoryCase)
                <option value="{{ $categoryCase->value }}">{{ $categoryCase->label() }}</option>
            @endforeach
        </select>
    </x-ui.table.search>

    <x-ui.table.per-page />

    <x-ui.card class="overflow-x-auto">
        <x-ui.table-skeleton wire:loading wire:target="search, category, sortBy, perPage, gotoPage, nextPage, previousPage" />
        <div wire:loading.remove wire:target="search, category, sortBy, perPage, gotoPage, nextPage, previousPage">
        @if ($articles->isEmpty() && ($search !== '' || $category !== ''))
            <x-ui.empty-state :title="__('No articles match')" :message="__('Try searching for something else.')" />
        @elseif ($articles->isEmpty())
            <x-ui.empty-state :title="__('No articles yet')" :message="__('Run the seeder for the starter set, or add one above.')" />
        @else
            <table class="w-full min-w-[720px] text-[13px]">
                <thead class="sticky top-0 z-10 bg-surface">
                    <tr class="border-b border-line text-left text-ink-soft">
                        <x-ui.table.sortable field="category" default="asc" :sort="$sort" :direction="$direction">{{ __('Category') }}</x-ui.table.sortable>
                        <x-ui.table.sortable field="title" :sort="$sort" :direction="$direction">{{ __('Title') }}</x-ui.table.sortable>
                        <x-ui.table.sortable field="position" :sort="$sort" :direction="$direction" class="px-3 py-5 text-right font-medium">{{ __('Position') }}</x-ui.table.sortable>
                        <x-ui.table.sortable field="views" :sort="$sort" :direction="$direction" class="px-3 py-5 text-right font-medium">{{ __('Views') }}</x-ui.table.sortable>
                        <x-ui.table.sortable field="published" :sort="$sort" :direction="$direction">{{ __('Published') }}</x-ui.table.sortable>
                        <th scope="col" class="px-3 py-5 text-right font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($articles as $article)
                        <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="article-{{ $article->id }}">
                            <td class="px-3 py-2">
                                <x-ui.badge variant="neutral">{{ $article->category->label() }}</x-ui.badge>
                            </td>
                            <td class="px-3 py-2 font-medium text-ink">
                                {{ $article->getTranslation('title', 'en') }}
                                @if ($article->draft !== null)
                                    <x-ui.badge variant="neutral" class="ml-1">{{ __('Draft') }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $article->position }}</td>
                            <td class="px-3 py-2 text-right font-mono tabular-nums">{{ number_format($article->views) }}</td>
                            <td class="px-3 py-2">
                                <button type="button" role="switch" aria-checked="{{ $article->is_active ? 'true' : 'false' }}"
                                        wire:click="toggleActive({{ $article->id }})"
                                        aria-label="{{ __('Toggle :title', ['title' => $article->getTranslation('title', 'en')]) }}"
                                        class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-[var(--radius-control)] focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                    <span class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-150 {{ $article->is_active ? 'bg-emerald' : 'bg-line-strong' }}">
                                        <span class="inline-block size-4 rounded-full bg-white transition-transform duration-150 {{ $article->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                    </span>
                                </button>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center justify-end gap-1">
                                    <x-ui.draft-actions type="article" :id="$article->id" :draft="$article->draft" :preview-path="'/help/article/'.$article->id" />
                                    <x-ui.button variant="ghost" :href="route('admin.support.articles.edit', $article)">{{ __('Edit') }}</x-ui.button>
                                    <button type="button" wire:click="delete({{ $article->id }})"
                                            wire:confirm="{{ __('Delete this article? This cannot be undone.') }}"
                                            class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 font-medium text-danger hover:bg-danger-tint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Delete') }}</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        </div>
    </x-ui.card>

    <x-ui.table.footer :rows="$articles" />
</div>
