<div class="mx-auto w-full max-w-3xl px-4 py-12 sm:py-16">
    {{-- Breadcrumb --}}
    <nav aria-label="{{ __('Breadcrumb') }}" class="text-[13px]">
        <a href="{{ route('help.index') }}" wire:navigate
           class="inline-flex min-h-11 items-center gap-1.5 font-medium text-ink-soft transition-colors hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
            {{ __('Help centre') }}
        </a>
        <span class="mx-1 text-ink-faint">/</span>
        <span class="text-ink-soft">{{ $article->category->label() }}</span>
    </nav>

    <h1 class="mt-4 font-display text-3xl font-bold sm:text-4xl">{{ $article->getTranslation('title', app()->getLocale()) }}</h1>

    {{-- Admin-authored HTML, sanitized to the tag allowlist on save. --}}
    <div class="mt-8 text-base leading-relaxed text-ink
                [&_h2]:mt-8 [&_h2]:mb-3 [&_h2]:font-display [&_h2]:text-xl [&_h2]:font-bold
                [&_h3]:mt-6 [&_h3]:mb-2 [&_h3]:font-display [&_h3]:text-lg [&_h3]:font-semibold
                [&_p]:my-4
                [&_a]:font-medium [&_a]:text-emerald [&_a]:underline hover:[&_a]:text-emerald-deep
                [&_ul]:my-4 [&_ul]:list-disc [&_ul]:pl-6
                [&_ol]:my-4 [&_ol]:list-decimal [&_ol]:pl-6
                [&_li]:my-1
                [&_strong]:font-semibold">
        {!! $article->getTranslation('body', app()->getLocale()) !!}
    </div>

    {{-- Related articles --}}
    @if ($related->isNotEmpty())
        <section class="mt-12 border-t border-line pt-8">
            <h2 class="font-display text-xl font-bold">{{ __('Related articles') }}</h2>
            <ul class="mt-3 divide-y divide-line rounded-[10px] border border-line bg-surface">
                @foreach ($related as $relatedArticle)
                    <li wire:key="related-{{ $relatedArticle->id }}">
                        <a href="{{ route('help.article', $relatedArticle) }}" wire:navigate
                           class="flex min-h-11 items-center justify-between gap-3 px-4 py-3 text-sm font-medium text-ink transition-colors duration-150 hover:bg-paper focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                            <span>{{ $relatedArticle->getTranslation('title', app()->getLocale()) }}</span>
                            <svg class="size-4 shrink-0 text-ink-faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Contact support --}}
    <div class="mt-12 rounded-[10px] border border-line bg-surface p-6 text-center">
        <p class="text-sm text-ink-soft">{{ __('Didn’t answer your question?') }}</p>
        <x-ui.button :href="route('help.tickets')" class="mt-3">{{ __('Contact support') }}</x-ui.button>
    </div>
</div>
