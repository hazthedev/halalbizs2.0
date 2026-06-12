<div class="space-y-4">

    {{-- Header --}}
    <div class="flex flex-wrap items-center gap-3">
        @php $logoUrl = $store->getFirstMediaUrl('logo'); @endphp
        @if ($logoUrl !== '')
            <img src="{{ $logoUrl }}" alt="{{ $store->name }}" class="size-12 rounded-[10px] border border-line bg-paper object-cover">
        @else
            <span class="flex size-12 items-center justify-center rounded-[10px] border border-line bg-paper font-display text-lg font-bold text-ink-faint" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($store->name, 0, 1)) }}</span>
        @endif

        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="font-display text-[22px] font-bold leading-tight">{{ $store->name }}</h1>
                <x-store-status-pill :status="$store->status" />
            </div>
            <p class="text-[13px] text-ink-soft">
                {{ __('Owner: :name (:email)', ['name' => $store->user->name, 'email' => $store->user->email]) }}
                · {{ __('Joined :date', ['date' => $store->created_at->format('j M Y')]) }}
            </p>
        </div>

        <div class="ml-auto flex items-center gap-2">
            <a href="{{ route('admin.sellers.stores') }}" wire:navigate
               class="inline-flex min-h-11 items-center rounded-lg px-3 text-[13px] font-medium text-ink-soft hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                {{ __('All stores') }}
            </a>
            @if ($store->isApproved())
                <a href="{{ $store->subdomainUrl() }}" target="_blank" rel="noopener"
                   class="inline-flex min-h-11 items-center gap-1.5 rounded-lg border border-ink px-3 text-[13px] font-semibold text-ink hover:bg-paper focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                    {{ __('View storefront') }}
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                </a>
            @endif
        </div>
    </div>

    @if ($store->status === \App\Enums\StoreStatus::Suspended && $store->rejection_reason)
        <x-ui.card class="border-danger bg-danger-tint p-4">
            <p class="text-[13px] font-semibold text-danger">{{ __('Suspended: :reason', ['reason' => $store->rejection_reason]) }}</p>
        </x-ui.card>
    @endif

    {{-- Stats row --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-ui.card class="p-4">
            <p class="text-[13px] font-medium text-ink-soft">{{ __('Live products') }}</p>
            <p class="mt-1 font-display text-[28px] font-bold leading-tight tabular-nums">{{ number_format($liveProductsCount) }}</p>
        </x-ui.card>
        <x-ui.card class="p-4">
            <p class="text-[13px] font-medium text-ink-soft">{{ __('Sub-orders') }}</p>
            <p class="mt-1 font-display text-[28px] font-bold leading-tight tabular-nums">{{ number_format($subOrdersCount) }}</p>
        </x-ui.card>
        <x-ui.card class="p-4">
            <p class="text-[13px] font-medium text-ink-soft">{{ __('GMV (completed)') }}</p>
            <p class="mt-1 font-display text-[28px] font-bold leading-tight tabular-nums">@money($gmvSen)</p>
        </x-ui.card>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">

        {{-- Moderation actions --}}
        <x-ui.card class="p-4">
            <h2 class="text-sm font-semibold">{{ __('Moderation') }}</h2>

            @if ($store->status === \App\Enums\StoreStatus::Suspended)
                <p class="mt-2 text-[13px] text-ink-soft">{{ __('Reinstating puts the shop and its live listings back on the storefront.') }}</p>
                <div class="mt-3">
                    <x-ui.button
                        variant="secondary"
                        wire:click="reinstate"
                        wire:confirm="{{ __('Reinstate this store? It goes back to approved and live immediately.') }}"
                        wire:loading.attr="disabled">
                        {{ __('Reinstate store') }}
                    </x-ui.button>
                </div>
            @else
                <div class="mt-2">
                    <label for="suspend-reason" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Suspension reason (emailed to the owner)') }}</label>
                    <textarea id="suspend-reason"
                              wire:model="suspendReason"
                              rows="2"
                              class="block w-full rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('suspendReason') ? 'border-danger' : 'border-line-strong' }}"
                              placeholder="{{ __('e.g. Repeated counterfeit listings after two warnings.') }}"></textarea>
                    @error('suspendReason')
                        <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
                    @enderror
                </div>
                <div class="mt-3">
                    <x-ui.button
                        variant="danger"
                        wire:click="suspend"
                        wire:confirm="{{ __('Suspend this store? It disappears from the storefront and the owner is emailed the reason.') }}"
                        wire:loading.attr="disabled">
                        {{ __('Suspend store') }}
                    </x-ui.button>
                </div>
            @endif
        </x-ui.card>

        {{-- Commission override --}}
        <x-ui.card class="p-4">
            <h2 class="text-sm font-semibold">{{ __('Commission override') }}</h2>
            <p class="mt-1 text-[13px] text-ink-soft">{{ __('Leave empty to inherit the category/global rate. Applies to new orders only — placed orders keep their snapshot.') }}</p>

            <div class="mt-3 flex flex-wrap items-end gap-2">
                <div class="w-36">
                    <label for="commission-rate" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Rate (%)') }}</label>
                    <input id="commission-rate"
                           type="number"
                           inputmode="decimal"
                           step="0.01"
                           min="0"
                           max="100"
                           wire:model="commissionRate"
                           placeholder="{{ __('Inherit') }}"
                           class="block min-h-11 w-full rounded-lg border bg-surface px-3.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('commissionRate') ? 'border-danger' : 'border-line-strong' }}">
                </div>
                <x-ui.button variant="secondary" wire:click="saveCommission" wire:loading.attr="disabled">
                    {{ __('Save override') }}
                </x-ui.button>
            </div>
            @error('commissionRate')
                <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
            @enderror
        </x-ui.card>
    </div>

    {{-- Documents re-verification --}}
    <x-ui.card class="p-4">
        <h2 class="text-sm font-semibold">{{ __('Documents') }}</h2>
        <p class="mt-1 text-[13px] text-ink-soft">{{ __('Re-verify or reject documents at any time — useful when certificates expire.') }}</p>
        <div class="mt-3">
            @include('livewire.admin.sellers.partials.documents', ['documents' => $store->documents])
        </div>
    </x-ui.card>
</div>
