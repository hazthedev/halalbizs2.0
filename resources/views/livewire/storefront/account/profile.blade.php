<x-account-shell active="profile" :title="__('Profile')">
    <div class="space-y-6">
        {{-- Account details --}}
        <x-ui.card class="p-6">
            <h2 class="font-display text-xl font-semibold">{{ __('Account details') }}</h2>

            <form wire:submit="updateProfile" class="mt-5 space-y-4">
                <x-ui.input
                    :label="__('Name')"
                    name="name"
                    wire:model="name"
                    autocomplete="name"
                    required
                    :error="$errors->first('name')"
                />

                {{-- Email is fixed for now — shown read-only with its verification state --}}
                <div>
                    <span class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Email') }}</span>
                    <div class="flex min-h-11 items-center justify-between gap-3 rounded-lg border border-line bg-paper px-3.5 py-2.5">
                        <span class="truncate text-sm text-ink-soft">{{ auth()->user()->email }}</span>
                        @if (auth()->user()->hasVerifiedEmail())
                            <x-ui.badge variant="verified">
                                <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                {{ __('Verified') }}
                            </x-ui.badge>
                        @else
                            <a href="{{ route('verification.notice') }}" wire:navigate class="shrink-0 text-[13px] font-medium text-emerald hover:text-emerald-deep">{{ __('Resend verification') }}</a>
                        @endif
                    </div>
                </div>

                <x-ui.input
                    :label="__('Phone (optional)')"
                    name="phone"
                    type="tel"
                    wire:model="phone"
                    autocomplete="tel"
                    placeholder="012-345 6789"
                    :error="$errors->first('phone')"
                />

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="preferred_locale" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Language') }}</label>
                        <select id="preferred_locale" wire:model="preferred_locale"
                                class="block min-h-11 w-full rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('preferred_locale') ? 'border-danger' : 'border-line-strong' }}">
                            @foreach ($locales as $locale)
                                <option value="{{ $locale }}">{{ $locale === 'ms' ? 'Bahasa Melayu' : 'English' }}</option>
                            @endforeach
                        </select>
                        @error('preferred_locale')
                            <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="preferred_currency" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Currency') }}</label>
                        <select id="preferred_currency" wire:model="preferred_currency"
                                class="block min-h-11 w-full rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('preferred_currency') ? 'border-danger' : 'border-line-strong' }}">
                            @foreach ($currencies as $currency)
                                <option value="{{ $currency }}">{{ $currency }}</option>
                            @endforeach
                        </select>
                        @error('preferred_currency')
                            <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="pt-1">
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="updateProfile">{{ __('Save changes') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        {{-- Password --}}
        <x-ui.card class="p-6">
            <h2 class="font-display text-xl font-semibold">{{ __('Change password') }}</h2>

            <form wire:submit="updatePassword" class="mt-5 space-y-4">
                <x-ui.input
                    :label="__('Current password')"
                    name="current_password"
                    type="password"
                    wire:model="current_password"
                    autocomplete="current-password"
                    required
                    :error="$errors->first('current_password')"
                />

                <x-ui.input
                    :label="__('New password')"
                    name="password"
                    type="password"
                    wire:model="password"
                    autocomplete="new-password"
                    required
                    :error="$errors->first('password')"
                    :hint="$errors->first('password') ? null : __('At least 8 characters.')"
                />

                <x-ui.input
                    :label="__('Confirm new password')"
                    name="password_confirmation"
                    type="password"
                    wire:model="password_confirmation"
                    autocomplete="new-password"
                    required
                    :error="$errors->first('password_confirmation')"
                />

                <div class="pt-1">
                    <x-ui.button type="submit" variant="secondary" wire:loading.attr="disabled" wire:target="updatePassword">{{ __('Update password') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        {{-- Privacy (PDPA) --}}
        <x-ui.card class="p-6">
            <h2 class="font-display text-xl font-semibold">{{ __('Privacy') }}</h2>

            <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-ink">{{ __('Download my data') }}</p>
                    <p class="mt-0.5 text-[13px] text-ink-soft">{{ __('A JSON file with your profile, addresses and full order history.') }}</p>
                </div>
                <x-ui.button variant="secondary" wire:click="downloadData" wire:loading.attr="disabled" wire:target="downloadData">
                    {{ __('Download') }}
                </x-ui.button>
            </div>

            {{-- Danger zone --}}
            <div class="mt-6 rounded-lg border border-danger/40 bg-danger-tint/40 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-danger">{{ __('Delete account') }}</p>
                        <p class="mt-0.5 max-w-md text-[13px] text-ink-soft">{{ __('Your name, email and phone are anonymized and you are signed out. Order records are kept — we are legally required to retain financial records.') }}</p>
                    </div>
                    <x-ui.button variant="danger" wire:click="$set('showDeleteModal', true)">{{ __('Delete my account') }}</x-ui.button>
                </div>
            </div>
        </x-ui.card>
    </div>

    {{-- Delete-account confirm modal --}}
    @if ($showDeleteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             role="dialog" aria-modal="true" aria-labelledby="delete-account-title"
             x-data x-on:keydown.escape.window="$wire.set('showDeleteModal', false)">
            <div class="absolute inset-0 bg-ink/50" wire:click="$set('showDeleteModal', false)" aria-hidden="true"></div>

            {{-- shadow allowed: overlay surface --}}
            <x-ui.card class="relative w-full max-w-md p-6 shadow-xl">
                <h3 id="delete-account-title" class="font-display text-xl font-semibold text-danger">{{ __('Delete this account?') }}</h3>
                <p class="mt-2 text-[13px] text-ink-soft">{{ __('This cannot be undone. Your personal details are anonymized immediately; order records stay for legal and financial reasons.') }}</p>

                <div class="mt-4 space-y-4">
                    <x-ui.input
                        :label="__('Type DELETE to confirm')"
                        name="delete_confirm"
                        wire:model="delete_confirm"
                        autocomplete="off"
                        placeholder="DELETE"
                        :error="$errors->first('delete_confirm')"
                    />

                    <x-ui.input
                        :label="__('Your password')"
                        name="delete_password"
                        type="password"
                        wire:model="delete_password"
                        autocomplete="current-password"
                        :error="$errors->first('delete_password')"
                    />
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <x-ui.button variant="ghost" wire:click="$set('showDeleteModal', false)">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button variant="danger-fill" wire:click="deleteAccount" wire:loading.attr="disabled" wire:target="deleteAccount"
                                 wire:confirm="{{ __('Last check — delete this account for good?') }}">
                        {{ __('Delete account') }}
                    </x-ui.button>
                </div>
            </x-ui.card>
        </div>
    @endif
</x-account-shell>
