<div class="mx-auto w-full max-w-5xl px-4 py-6 lg:py-8">
    <x-ui.section-heading as="h1" :title="__('New session')" />
        <form wire:submit="save" class="mt-5 grid gap-4 rounded-[var(--radius-card)] border border-line bg-surface p-5 shadow-soft sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="ls-title" class="text-[13px] font-medium">{{ __('Title') }}</label>
                <input id="ls-title" type="text" wire:model="title" maxlength="120" class="mt-1 block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-canvas px-3 text-sm">
                @error('title') <p class="mt-1 text-[13px] text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="ls-video" class="text-[13px] font-medium">{{ __('Video URL (YouTube / Facebook)') }}</label>
                <input id="ls-video" type="url" wire:model.live.blur="videoUrl" placeholder="https://youtube.com/watch?v=…" class="mt-1 block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-canvas px-3 text-sm">
                @if ($detectedPlatform && ! $errors->has('videoUrl'))
                    <p class="mt-1 text-[13px] text-emerald">{{ __('Detected: :platform', ['platform' => ucfirst($detectedPlatform)]) }}</p>
                @endif
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
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-[var(--radius-control)] bg-emerald px-5 text-sm font-medium text-white hover:bg-emerald-deep">{{ __('Create') }}</button>
                <x-ui.button variant="secondary" :href="route('seller.live.index')">{{ __('Cancel') }}</x-ui.button>
            </div>
        </form>
</div>
