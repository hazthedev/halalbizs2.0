<div class="mx-auto w-full max-w-2xl px-4 py-12 sm:py-16">
    @if ($store !== null)
        @if ($store->status === \App\Enums\StoreStatus::Pending)
            <x-ui.card class="p-6 sm:p-8">
                <div class="flex size-12 items-center justify-center rounded-full bg-emerald-tint">
                    <svg class="size-6 text-emerald" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                </div>
                <h1 class="mt-4 font-display text-[28px] font-bold leading-tight">{{ __('Application received') }}</h1>
                <p class="mt-2 text-sm text-ink-soft">
                    {{ __('Thanks for applying to sell on :app — your shop “:store” is now in the review queue.', ['app' => config('app.name'), 'store' => $store->name]) }}
                </p>

                <h2 class="mt-6 text-[13px] font-semibold uppercase tracking-[0.04em] text-ink-soft">{{ __('What happens next') }}</h2>
                <ol class="mt-3 space-y-3 text-sm text-ink">
                    <li class="flex gap-3">
                        <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-paper font-mono text-[11px] font-semibold">1</span>
                        {{ __('Our team verifies your SSM certificate, IC and bank details.') }}
                    </li>
                    <li class="flex gap-3">
                        <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-paper font-mono text-[11px] font-semibold">2</span>
                        {{ __('Reviews usually take 2–3 business days.') }}
                    </li>
                    <li class="flex gap-3">
                        <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-paper font-mono text-[11px] font-semibold">3</span>
                        {{ __('You\'ll get an email the moment a decision is made — once approved, your Seller Centre unlocks right here.') }}
                    </li>
                </ol>

                <div class="mt-6 border-t border-line pt-5">
                    <x-ui.button variant="secondary" :href="route('home')">{{ __('Back to shopping') }}</x-ui.button>
                </div>
            </x-ui.card>

        @elseif ($store->status === \App\Enums\StoreStatus::Rejected)
            <x-ui.card class="p-6 sm:p-8">
                <div class="flex size-12 items-center justify-center rounded-full bg-danger-tint">
                    <svg class="size-6 text-danger" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </div>
                <h1 class="mt-4 font-display text-[28px] font-bold leading-tight">{{ __('Application rejected') }}</h1>
                <p class="mt-2 text-sm text-ink-soft">{{ __('Your application for “:store” wasn\'t approved this time.', ['store' => $store->name]) }}</p>

                @if ($store->rejection_reason)
                    <div class="mt-4 rounded-lg border border-danger/30 bg-danger-tint p-4">
                        <p class="text-[13px] font-semibold text-danger">{{ __('Reason') }}</p>
                        <p class="mt-1 text-sm text-ink">{{ $store->rejection_reason }}</p>
                    </div>
                @endif

                <p class="mt-4 text-sm text-ink-soft">{{ __('Fix the issue above and re-apply — re-applying clears this application so you can start fresh.') }}</p>

                <div class="mt-6 flex flex-wrap gap-3 border-t border-line pt-5">
                    <x-ui.button
                        wire:click="reapply"
                        wire:confirm="{{ __('This deletes your previous application and its documents. Continue?') }}"
                        wire:loading.attr="disabled"
                    >
                        {{ __('Re-apply') }}
                    </x-ui.button>
                    <x-ui.button variant="ghost" :href="route('home')">{{ __('Back to shopping') }}</x-ui.button>
                </div>
            </x-ui.card>

        @elseif ($store->status === \App\Enums\StoreStatus::Suspended)
            <x-ui.card class="p-6 sm:p-8">
                <div class="flex size-12 items-center justify-center rounded-full bg-warn-tint">
                    <svg class="size-6 text-warn" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                </div>
                <h1 class="mt-4 font-display text-[28px] font-bold leading-tight">{{ __('Your shop is suspended') }}</h1>
                <p class="mt-2 text-sm text-ink-soft">{{ __('“:store” is currently suspended — buyers can\'t see your products and you can\'t access the Seller Centre.', ['store' => $store->name]) }}</p>

                @if ($store->rejection_reason)
                    <div class="mt-4 rounded-lg border border-warn/30 bg-warn-tint p-4">
                        <p class="text-[13px] font-semibold text-warn">{{ __('Reason') }}</p>
                        <p class="mt-1 text-sm text-ink">{{ $store->rejection_reason }}</p>
                    </div>
                @endif

                <p class="mt-4 text-sm text-ink-soft">{{ __('If you believe this is a mistake, contact support and we\'ll look into it.') }}</p>

                <div class="mt-6 border-t border-line pt-5">
                    <x-ui.button variant="secondary" :href="route('home')">{{ __('Back to shopping') }}</x-ui.button>
                </div>
            </x-ui.card>
        @endif
    @endif
</div>
