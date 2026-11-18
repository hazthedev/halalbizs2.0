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

    <x-ui.card class="overflow-x-auto">
        @if ($articles->isEmpty())
            <x-ui.empty-state :title="__('No articles yet')" :message="__('Run the seeder for the starter set, or add one above.')" />
        @else
            <table class="w-full min-w-[720px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Category') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Title') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Position') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Views') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Published') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Actions') }}</th>
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
    </x-ui.card>
</div>
