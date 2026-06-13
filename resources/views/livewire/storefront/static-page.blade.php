<div class="mx-auto w-full max-w-3xl px-4 py-12 sm:py-16">
    <x-ui.section-heading as="h1" :title="$page->getTranslation('title', app()->getLocale())" />

    {{-- Trusted, admin-seeded HTML (docs/05 §B9) — prose-styled by hand, no typography plugin. --}}
    <div class="mt-8 text-base leading-relaxed text-ink
                [&_h2]:mt-8 [&_h2]:mb-3 [&_h2]:font-display [&_h2]:text-xl [&_h2]:font-bold
                [&_h3]:mt-6 [&_h3]:mb-2 [&_h3]:font-display [&_h3]:text-lg [&_h3]:font-semibold
                [&_p]:my-4
                [&_a]:font-medium [&_a]:text-emerald [&_a]:underline hover:[&_a]:text-emerald-deep
                [&_ul]:my-4 [&_ul]:list-disc [&_ul]:pl-6
                [&_ol]:my-4 [&_ol]:list-decimal [&_ol]:pl-6
                [&_li]:my-1
                [&_strong]:font-semibold
                [&_hr]:my-8 [&_hr]:border-line">
        {!! $page->getTranslation('body', app()->getLocale()) !!}
    </div>
</div>
