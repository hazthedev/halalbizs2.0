<div class="mx-auto w-full max-w-3xl px-4 py-12 sm:py-16">
    <h1 class="font-display text-3xl font-bold sm:text-4xl">{{ __('Help centre') }}</h1>
    <p class="mt-2 text-sm text-ink-soft">{{ __('Answers about ordering, payments, shipping, returns, and selling.') }}</p>

    {{-- Search --}}
    <div class="relative mt-6">
        <svg class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-ink-faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
        <label for="help-search" class="sr-only">{{ __('Search help articles') }}</label>
        <input
            id="help-search"
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="{{ __('Search help articles…') }}"
            class="block min-h-11 w-full rounded-lg border border-line-strong bg-surface py-2.5 pl-10 pr-3.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald"
        >
    </div>

    {{-- Category groups --}}
    <div class="mt-8 space-y-8" wire:loading.class="opacity-60" wire:target="search">
        @forelse ($groups as $group)
            <section wire:key="help-cat-{{ $group['category']->value }}">
                <h2 class="font-display text-xl font-bold">{{ $group['category']->label() }}</h2>
                <ul class="mt-3 divide-y divide-line rounded-[10px] border border-line bg-surface">
                    @foreach ($group['articles'] as $article)
                        <li wire:key="help-article-{{ $article->id }}">
                            <a href="{{ route('help.article', $article) }}" wire:navigate
                               class="flex min-h-11 items-center justify-between gap-3 px-4 py-3 text-sm font-medium text-ink transition-colors duration-150 hover:bg-paper focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                <span>{{ $article->getTranslation('title', app()->getLocale()) }}</span>
                                <svg class="size-4 shrink-0 text-ink-faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <div class="rounded-[10px] border border-line bg-surface px-6 py-16 text-center">
                <h2 class="font-display text-xl font-semibold">
                    {{ $searching ? __('Nothing matches that search') : __('No articles yet') }}
                </h2>
                <p class="mt-1 text-sm text-ink-soft">
                    {{ $searching ? __('Try a different word, or ask us directly below.') : __('Help articles are on their way.') }}
                </p>
            </div>
        @endforelse
    </div>

    {{-- Contact support CTA --}}
    <div class="mt-12 rounded-[10px] border border-line bg-surface p-6 text-center sm:p-8">
        <h2 class="font-display text-xl font-bold">{{ __('Still stuck?') }}</h2>
        <p class="mt-1 text-sm text-ink-soft">{{ __('Open a ticket and our support team will get back to you.') }}</p>
        <x-ui.button :href="route('help.tickets')" class="mt-4">{{ __('Contact support') }}</x-ui.button>
    </div>
</div>
