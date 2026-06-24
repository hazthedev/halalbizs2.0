<div class="mx-auto w-full max-w-5xl px-4 py-6 lg:py-8">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="font-display text-2xl font-bold">{{ __('Live shopping') }}</h1>
            <p class="mt-1 text-[13px] text-ink-soft">{{ __('Host a shoppable live session with a product rail and a pinned voucher.') }}</p>
        </div>
        <button type="button" wire:click="create"
                class="inline-flex min-h-11 items-center justify-center rounded-[var(--radius-control)] bg-emerald px-4 text-sm font-semibold text-white hover:bg-emerald-deep focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-1">
            {{ __('New session') }}
        </button>
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-5 grid gap-4 rounded-[var(--radius-card)] border border-line bg-surface p-5 shadow-soft sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="ls-title" class="text-[13px] font-medium">{{ __('Title') }}</label>
                <input id="ls-title" type="text" wire:model="title" maxlength="120" class="mt-1 block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-canvas px-3 text-sm">
                @error('title') <p class="mt-1 text-[13px] text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="ls-video" class="text-[13px] font-medium">{{ __('Video URL (YouTube / Facebook)') }}</label>
                <input id="ls-video" type="url" wire:model="videoUrl" placeholder="https://youtube.com/watch?v=…" class="mt-1 block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-canvas px-3 text-sm">
                @error('videoUrl') <p class="mt-1 text-[13px] text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="ls-voucher" class="text-[13px] font-medium">{{ __('Pinned voucher code (optional)') }}</label>
                <input id="ls-voucher" type="text" wire:model="voucherCode" class="mt-1 block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-canvas px-3 font-mono text-sm uppercase">
                @error('voucherCode') <p class="mt-1 text-[13px] text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="ls-when" class="text-[13px] font-medium">{{ __('Scheduled for (optional)') }}</label>
                <input id="ls-when" type="datetime-local" wire:model="scheduledFor" class="mt-1 block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-canvas px-3 text-sm">
            </div>
            <div class="flex gap-2 sm:col-span-2">
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-[var(--radius-control)] bg-emerald px-5 text-sm font-semibold text-white hover:bg-emerald-deep">{{ __('Create') }}</button>
                <button type="button" wire:click="$set('showForm', false)" class="inline-flex min-h-11 items-center justify-center rounded-[var(--radius-control)] border border-line-strong bg-surface px-5 text-sm font-semibold text-ink hover:border-ink">{{ __('Cancel') }}</button>
            </div>
        </form>
    @endif

    <div class="mt-6 space-y-3">
        @forelse ($sessions as $session)
            <div class="rounded-[var(--radius-card)] border border-line bg-surface shadow-soft">
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-ink">{{ $session->title }}</p>
                        <p class="text-[13px] text-ink-soft">{{ trans_choice(':count product|:count products', $session->products->count(), ['count' => $session->products->count()]) }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-ui.badge :variant="$session->status === \App\Enums\LiveSessionStatus::Live ? 'success' : 'neutral'">{{ $session->status->label() }}</x-ui.badge>
                        @if ($session->status === \App\Enums\LiveSessionStatus::Live)
                            <a href="{{ route('live.room', $session->slug) }}" target="_blank" class="text-[13px] font-semibold text-emerald hover:text-emerald-deep">{{ __('Open room') }}</a>
                            <button type="button" wire:click="end({{ $session->id }})" class="text-[13px] font-semibold text-ink-soft hover:text-danger">{{ __('End') }}</button>
                        @elseif ($session->status === \App\Enums\LiveSessionStatus::Scheduled)
                            <button type="button" wire:click="goLive({{ $session->id }})" class="inline-flex min-h-9 items-center rounded-[var(--radius-control)] bg-emerald px-3 text-[13px] font-semibold text-white hover:bg-emerald-deep">{{ __('Go live') }}</button>
                        @endif
                        <button type="button" wire:click="manage({{ $session->id }})" class="text-[13px] font-semibold text-ink-soft hover:text-ink">{{ $managing?->id === $session->id ? __('Close') : __('Manage rail') }}</button>
                    </div>
                </div>

                @if ($managing?->id === $session->id)
                    <div class="border-t border-line px-5 py-4">
                        {{-- Add product --}}
                        <div class="flex flex-wrap items-end gap-2">
                            <label class="min-w-0 flex-1">
                                <span class="text-[13px] font-medium">{{ __('Add a product') }}</span>
                                <select wire:model="addProductId" class="mt-1 block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-canvas px-3 text-sm">
                                    <option value="">{{ __('Choose…') }}</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->getTranslation('name', 'en') }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <button type="button" wire:click="addProduct" class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] border border-line-strong bg-surface px-4 text-sm font-semibold text-ink hover:border-ink">{{ __('Add') }}</button>
                        </div>

                        {{-- Rail --}}
                        @if ($managing->products->isNotEmpty())
                            <ul class="mt-3 divide-y divide-line">
                                @foreach ($managing->products as $product)
                                    <li class="flex items-center justify-between gap-3 py-2.5">
                                        <span class="min-w-0 truncate text-[13px] text-ink">{{ $product->getTranslation('name', 'en') }}</span>
                                        <div class="flex items-center gap-3">
                                            @if ($managing->featured_product_id === $product->id)
                                                <span class="text-[11px] font-semibold uppercase tracking-[0.06em] text-emerald">{{ __('Featured') }}</span>
                                            @else
                                                <button type="button" wire:click="feature({{ $managing->id }}, {{ $product->id }})" class="text-[13px] font-semibold text-ink-soft hover:text-emerald">{{ __('Feature') }}</button>
                                            @endif
                                            <button type="button" wire:click="removeProduct({{ $managing->id }}, {{ $product->id }})" class="text-[13px] font-semibold text-ink-soft hover:text-danger">{{ __('Remove') }}</button>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-3 text-[13px] text-ink-soft">{{ __('No products on the rail yet.') }}</p>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-[var(--radius-card)] border border-line bg-surface p-10 text-center shadow-soft">
                <p class="text-sm text-ink-soft">{{ __('No live sessions yet.') }}</p>
            </div>
        @endforelse
    </div>
</div>
