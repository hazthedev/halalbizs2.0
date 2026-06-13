<div class="space-y-4">

    {{-- Header --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="font-display text-[22px] font-bold leading-tight">{{ $user->name }}</h1>
                @if ($user->isSuspended())
                    <x-ui.badge variant="danger">{{ __('Suspended') }}</x-ui.badge>
                @else
                    <x-ui.badge variant="sale">{{ __('Active') }}</x-ui.badge>
                @endif
            </div>
            <p class="text-[13px] text-ink-soft">{{ __('Joined :date', ['date' => $user->created_at->format('j M Y')]) }}</p>
        </div>
        <a href="{{ route('admin.buyers.index') }}" wire:navigate
           class="ml-auto inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-3 text-[13px] font-medium text-ink-soft hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
            {{ __('All buyers') }}
        </a>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">

        {{-- Profile card --}}
        <x-ui.card class="p-4">
            <h2 class="text-sm font-semibold">{{ __('Profile') }}</h2>
            <dl class="mt-2 space-y-1.5 text-[13px]">
                <div class="flex justify-between gap-3">
                    <dt class="text-ink-soft">{{ __('Email') }}</dt>
                    <dd class="text-right font-medium">{{ $user->email }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-ink-soft">{{ __('Email verified') }}</dt>
                    <dd class="text-right">{{ $user->email_verified_at !== null ? $user->email_verified_at->format('j M Y') : __('No') }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-ink-soft">{{ __('Phone') }}</dt>
                    <dd class="text-right">{{ $user->phone ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-ink-soft">{{ __('Locale') }}</dt>
                    <dd class="text-right">{{ $user->preferred_locale ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-ink-soft">{{ __('Currency') }}</dt>
                    <dd class="text-right">{{ $user->preferred_currency ?? '—' }}</dd>
                </div>
            </dl>
        </x-ui.card>

        {{-- Orders summary --}}
        <x-ui.card class="p-4">
            <h2 class="text-sm font-semibold">{{ __('Orders') }}</h2>
            <div class="mt-2 grid grid-cols-2 gap-3">
                <div>
                    <p class="text-[13px] font-medium text-ink-soft">{{ __('Total orders') }}</p>
                    <p class="mt-1 font-display text-[28px] font-bold leading-tight tabular-nums">{{ number_format($ordersCount) }}</p>
                </div>
                <div>
                    <p class="text-[13px] font-medium text-ink-soft">{{ __('Lifetime spend (paid)') }}</p>
                    <p class="mt-1 font-display text-[28px] font-bold leading-tight tabular-nums">@money($lifetimeSpendSen)</p>
                </div>
            </div>
        </x-ui.card>

        {{-- Account actions --}}
        <x-ui.card class="p-4">
            <h2 class="text-sm font-semibold">{{ __('Account') }}</h2>

            @if ($user->isSuspended())
                <p class="mt-2 text-[13px] text-ink-soft">{{ __('This account is suspended and cannot log in.') }}</p>
                <div class="mt-3">
                    <x-ui.button
                        variant="secondary"
                        wire:click="unsuspend"
                        wire:confirm="{{ __('Reinstate this account? They can log in again immediately.') }}"
                        wire:loading.attr="disabled">
                        {{ __('Unsuspend account') }}
                    </x-ui.button>
                </div>
            @else
                <div class="mt-2">
                    <label for="suspend-reason" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Suspension reason (kept in the audit log)') }}</label>
                    <textarea id="suspend-reason"
                              wire:model="suspendReason"
                              rows="2"
                              class="block w-full rounded-[var(--radius-control)] border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('suspendReason') ? 'border-danger' : 'border-line-strong' }}"
                              placeholder="{{ __('e.g. Chargeback abuse across multiple orders.') }}"></textarea>
                    @error('suspendReason')
                        <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
                    @enderror
                </div>
                <div class="mt-3">
                    <x-ui.button
                        variant="danger"
                        wire:click="suspend"
                        wire:confirm="{{ __('Suspend this account? They are blocked from logging in until reinstated.') }}"
                        wire:loading.attr="disabled">
                        {{ __('Suspend account') }}
                    </x-ui.button>
                </div>
            @endif

            <p class="mt-4 border-t border-line pt-3 text-[13px] text-ink-faint">{{ __('PDPA anonymization arrives in M8.') }}</p>
        </x-ui.card>
    </div>

    {{-- Addresses (read-only) --}}
    <x-ui.card class="p-4">
        <h2 class="text-sm font-semibold">{{ __('Addresses') }}</h2>

        @if ($user->addresses->isEmpty())
            <p class="mt-2 text-[13px] text-ink-soft">{{ __('No addresses saved.') }}</p>
        @else
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($user->addresses as $address)
                    <div class="rounded-[var(--radius-card)] border border-line p-3 text-[13px]" wire:key="address-{{ $address->id }}">
                        <div class="flex items-center gap-2">
                            <p class="font-semibold text-ink">{{ $address->label ?? __('Address') }}</p>
                            @if ($address->is_default)
                                <x-ui.badge variant="neutral">{{ __('Default') }}</x-ui.badge>
                            @endif
                        </div>
                        <p class="mt-1 text-ink">{{ $address->recipient_name }} · {{ $address->phone }}</p>
                        <p class="mt-0.5 leading-relaxed text-ink-soft">
                            {{ $address->line1 }}@if ($address->line2), {{ $address->line2 }}@endif<br>
                            {{ $address->postcode }} {{ $address->city }}, {{ $address->state }}
                        </p>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui.card>
</div>
