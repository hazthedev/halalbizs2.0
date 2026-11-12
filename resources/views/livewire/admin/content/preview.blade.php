<div class="space-y-4">
    <x-ui.section-heading :title="__('Preview')" as="h1" />
    <p class="text-[13px] text-ink-soft">{{ __('This is the real page with drafts switched on. Shoppers do not see it until a publisher publishes.') }}</p>

    <div class="flex flex-col gap-6 xl:flex-row xl:items-start">
        <section class="min-w-0 flex-1" aria-labelledby="preview-web">
            <h2 id="preview-web" class="mb-2 text-[13px] font-medium text-ink-soft">{{ __('Web') }}</h2>
            <iframe data-preview="web" src="{{ $src }}" title="{{ __('Web preview') }}"
                    class="h-[760px] w-full rounded-[var(--radius-card)] border border-line bg-white"></iframe>
        </section>
        <section class="shrink-0" aria-labelledby="preview-phone">
            <h2 id="preview-phone" class="mb-2 text-[13px] font-medium text-ink-soft">{{ __('Phone') }}</h2>
            <iframe data-preview="phone" src="{{ $src }}" title="{{ __('Phone preview') }}"
                    class="h-[760px] w-[390px] max-w-full rounded-[var(--radius-card)] border border-line bg-white"></iframe>
        </section>
    </div>
</div>
