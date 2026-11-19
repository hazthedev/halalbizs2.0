<div class="mx-auto w-full max-w-6xl">
    <x-ui.section-heading as="h1" :title="$content['title']" class="mb-8 [&_span]:whitespace-normal" />

    @include('partials.sop-guide', ['content' => $content, 'stickyTop' => 'lg:top-0'])
</div>
