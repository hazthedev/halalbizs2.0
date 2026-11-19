<div class="mx-auto w-full max-w-6xl px-4 py-12 sm:py-16">
    <a href="{{ route('help.index') }}" wire:navigate class="inline-flex min-h-11 items-center text-sm font-medium text-emerald hover:text-emerald-deep">{{ __('Help centre') }}</a>
    <x-ui.section-heading as="h1" :title="$content['title']" class="mt-2 mb-8 sm:mb-12 [&_span]:whitespace-normal" />

    @include('partials.sop-guide', ['content' => $content, 'stickyTop' => 'lg:top-28'])
</div>
