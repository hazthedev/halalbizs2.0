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

        {{-- Security: 2FA + phone verification --}}
        <x-ui.card class="p-6" id="security">
            <h2 class="font-display text-xl font-semibold">{{ __('Security') }}</h2>

            {{-- ── Two-factor authentication ─────────────────────────── --}}
            <div class="mt-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-ink">{{ __('Two-factor authentication') }}</p>
                        <p class="mt-0.5 max-w-md text-[13px] text-ink-soft">{{ __('A second code at login keeps your account safe even if your password leaks.') }}</p>
                    </div>
                    @if (auth()->user()->hasTwoFactor())
                        <x-ui.badge variant="verified">
                            <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            {{ auth()->user()->two_factor_method->label() }}
                        </x-ui.badge>
                    @else
                        <x-ui.badge variant="neutral">{{ __('Off') }}</x-ui.badge>
                    @endif
                </div>

                {{-- Fresh recovery codes — shown exactly once --}}
                @if ($freshRecoveryCodes !== null)
                    @php($recoveryCopyText = implode("\n", $freshRecoveryCodes))
                    <div class="mt-4 rounded-lg border border-line bg-paper p-4">
                        <p class="text-sm font-semibold text-ink">{{ __('Your recovery codes') }}</p>
                        <p class="mt-0.5 text-[13px] text-ink-soft">{{ __('Save these somewhere safe — each works once, and this is the only time we show them.') }}</p>
                        <ul class="mt-3 grid grid-cols-2 gap-x-6 gap-y-1.5 font-mono text-[13px] text-ink sm:grid-cols-3">
                            @foreach ($freshRecoveryCodes as $recoveryCode)
                                <li class="select-all">{{ $recoveryCode }}</li>
                            @endforeach
                        </ul>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <x-ui.button variant="secondary" type="button"
                                         x-data="{ copied: false }"
                                         x-on:click="navigator.clipboard.writeText(@js($recoveryCopyText)); copied = true; setTimeout(() => copied = false, 2000)">
                                <span x-show="!copied">{{ __('Copy all') }}</span>
                                <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                            </x-ui.button>
                            <x-ui.button variant="ghost" wire:click="dismissRecoveryCodes">{{ __('I\'ve saved these') }}</x-ui.button>
                        </div>
                    </div>
                @endif

                @if (! auth()->user()->hasTwoFactor())
                    @if (! $emailSetupPending && $totpSecret === null)
                        <div class="mt-4 flex flex-wrap gap-2">
                            <x-ui.button variant="secondary" wire:click="startEmailTwoFactor" wire:loading.attr="disabled" wire:target="startEmailTwoFactor">
                                {{ __('Use email codes') }}
                            </x-ui.button>
                            <x-ui.button variant="secondary" wire:click="startTotpSetup" wire:loading.attr="disabled" wire:target="startTotpSetup">
                                {{ __('Use an authenticator app') }}
                            </x-ui.button>
                        </div>
                    @endif

                    {{-- Email-code setup --}}
                    @if ($emailSetupPending)
                        <form wire:submit="confirmEmailTwoFactor" class="mt-4 space-y-4 rounded-lg border border-line bg-paper p-4">
                            <p class="text-[13px] text-ink-soft">{{ __('We\'ve emailed you a 6-digit code — enter it below to turn on email codes.') }}</p>

                            <x-ui.input
                                :label="__('6-digit code')"
                                name="email_setup_code"
                                inputmode="numeric"
                                maxlength="6"
                                wire:model="email_setup_code"
                                autocomplete="one-time-code"
                                placeholder="123456"
                                class="max-w-[12rem] [&_input]:font-mono [&_input]:tracking-[0.3em]"
                                :error="$errors->first('email_setup_code')"
                            />

                            <div class="flex flex-wrap gap-2">
                                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="confirmEmailTwoFactor">{{ __('Turn on') }}</x-ui.button>
                                <x-ui.button variant="ghost" wire:click="startEmailTwoFactor" wire:loading.attr="disabled" wire:target="startEmailTwoFactor">{{ __('Resend code') }}</x-ui.button>
                                <x-ui.button variant="ghost" wire:click="cancelTwoFactorSetup">{{ __('Cancel') }}</x-ui.button>
                            </div>
                        </form>
                    @endif

                    {{-- Authenticator (TOTP) setup --}}
                    @if ($totpSecret !== null)
                        <form wire:submit="confirmTotpSetup" class="mt-4 space-y-4 rounded-lg border border-line bg-paper p-4">
                            <p class="text-[13px] text-ink-soft">{{ __('Add HalalBizs to your authenticator app (Google Authenticator, 1Password, Aegis…) by entering this secret, then confirm with the current code.') }}</p>

                            <div>
                                <span class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Secret key') }}</span>
                                <p class="select-all break-all rounded-lg border border-line bg-surface px-3.5 py-2.5 font-mono text-[13px] text-ink">{{ $totpSecret }}</p>
                            </div>

                            <div>
                                <span class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Setup link (otpauth)') }}</span>
                                <p class="select-all break-all rounded-lg border border-line bg-surface px-3.5 py-2.5 font-mono text-[12px] text-ink-soft">{{ $otpauthUri }}</p>
                                <button type="button"
                                        x-data="{ copied: false }"
                                        x-on:click="navigator.clipboard.writeText(@js($otpauthUri)); copied = true; setTimeout(() => copied = false, 2000)"
                                        class="mt-1.5 min-h-11 text-[13px] font-medium text-emerald hover:text-emerald-deep">
                                    <span x-show="!copied">{{ __('Copy setup link') }}</span>
                                    <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                                </button>
                            </div>

                            <x-ui.input
                                :label="__('Code from your app')"
                                name="totp_setup_code"
                                inputmode="numeric"
                                maxlength="6"
                                wire:model="totp_setup_code"
                                autocomplete="one-time-code"
                                placeholder="123456"
                                class="max-w-[12rem] [&_input]:font-mono [&_input]:tracking-[0.3em]"
                                :error="$errors->first('totp_setup_code')"
                            />

                            <div class="flex flex-wrap gap-2">
                                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="confirmTotpSetup">{{ __('Turn on') }}</x-ui.button>
                                <x-ui.button variant="ghost" wire:click="cancelTwoFactorSetup">{{ __('Cancel') }}</x-ui.button>
                            </div>
                        </form>
                    @endif
                @else
                    <div class="mt-4 space-y-4">
                        @if (auth()->user()->two_factor_method === \App\Enums\TwoFactorMethod::Totp)
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <p class="text-[13px] text-ink-soft">{{ __('Lost your recovery codes? Generate a fresh set — the old ones stop working.') }}</p>
                                <x-ui.button variant="secondary" wire:click="regenerateRecoveryCodes" wire:loading.attr="disabled" wire:target="regenerateRecoveryCodes">
                                    {{ __('Regenerate recovery codes') }}
                                </x-ui.button>
                            </div>
                        @endif

                        <form wire:submit="disableTwoFactor" class="flex flex-wrap items-end gap-3">
                            <x-ui.input
                                :label="__('Confirm your password to turn off')"
                                name="disable_password"
                                type="password"
                                wire:model="disable_password"
                                autocomplete="current-password"
                                required
                                class="w-full max-w-xs"
                                :error="$errors->first('disable_password')"
                            />
                            <x-ui.button type="submit" variant="danger" wire:loading.attr="disabled" wire:target="disableTwoFactor">
                                {{ __('Turn off 2FA') }}
                            </x-ui.button>
                        </form>
                    </div>
                @endif
            </div>

            {{-- ── Phone verification ─────────────────────────────────── --}}
            <div class="mt-6 border-t border-line pt-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-ink">{{ __('Phone number') }}</p>
                        <p class="mt-0.5 max-w-md text-[13px] text-ink-soft">{{ __('A verified number helps couriers and sellers reach you about deliveries.') }}</p>
                    </div>
                    @if (auth()->user()->hasVerifiedPhone())
                        <x-ui.badge variant="verified">
                            <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            {{ __('Verified') }}
                        </x-ui.badge>
                    @else
                        <x-ui.badge variant="neutral">{{ __('Not verified') }}</x-ui.badge>
                    @endif
                </div>

                <div class="mt-4 flex flex-wrap items-end gap-3">
                    <x-ui.input
                        :label="__('Malaysian mobile number')"
                        name="verify_phone"
                        type="tel"
                        wire:model="verify_phone"
                        autocomplete="tel"
                        placeholder="012-345 6789"
                        class="w-full max-w-xs"
                        :error="$errors->first('verify_phone')"
                    />
                    <x-ui.button variant="secondary" wire:click="sendPhoneCode" wire:loading.attr="disabled" wire:target="sendPhoneCode">
                        {{ auth()->user()->hasVerifiedPhone() ? __('Re-verify') : __('Send code') }}
                    </x-ui.button>
                </div>

                @if ($phoneOtpPending)
                    <form wire:submit="confirmPhoneCode" class="mt-4 flex flex-wrap items-end gap-3 rounded-lg border border-line bg-paper p-4">
                        <x-ui.input
                            :label="__('Code from the SMS')"
                            name="phone_otp_code"
                            inputmode="numeric"
                            maxlength="6"
                            wire:model="phone_otp_code"
                            autocomplete="one-time-code"
                            placeholder="123456"
                            class="w-full max-w-[12rem] [&_input]:font-mono [&_input]:tracking-[0.3em]"
                            :error="$errors->first('phone_otp_code')"
                        />
                        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="confirmPhoneCode">{{ __('Verify phone') }}</x-ui.button>
                    </form>
                @endif
            </div>
        </x-ui.card>

        {{-- Active sessions --}}
        <x-ui.card class="p-6" id="sessions">
            <h2 class="font-display text-xl font-semibold">{{ __('Active sessions') }}</h2>
            <p class="mt-1 max-w-md text-[13px] text-ink-soft">{{ __('Everywhere your account is currently logged in. Log the others out if you don\'t recognise one.') }}</p>

            <ul class="mt-5 divide-y divide-line">
                @forelse ($activeSessions as $session)
                    <li class="flex flex-wrap items-center gap-x-3 gap-y-1 py-3">
                        <svg class="size-5 shrink-0 text-ink-soft" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25"/></svg>
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-2 text-sm font-medium text-ink">
                                {{ $session->label }}
                                @if ($session->isCurrent)
                                    <x-ui.badge variant="verified">{{ __('This device') }}</x-ui.badge>
                                @endif
                            </p>
                            <p class="mt-0.5 text-[13px] text-ink-soft">
                                @if ($session->ip)<span class="font-mono text-[12px]">{{ $session->ip }}</span> · @endif{{ __('Last active :time', ['time' => $session->lastActive->diffForHumans()]) }}
                            </p>
                        </div>
                    </li>
                @empty
                    <li class="py-3 text-sm text-ink-soft">{{ __('Only this session is active right now.') }}</li>
                @endforelse
            </ul>

            <form wire:submit="logoutOtherDevices" class="mt-4 flex flex-wrap items-end gap-3 border-t border-line pt-4">
                <x-ui.input
                    :label="__('Confirm your password to log out other devices')"
                    name="logout_others_password"
                    type="password"
                    wire:model="logout_others_password"
                    autocomplete="current-password"
                    required
                    class="w-full max-w-xs"
                    :error="$errors->first('logout_others_password')"
                />
                <x-ui.button type="submit" variant="secondary" wire:loading.attr="disabled" wire:target="logoutOtherDevices">
                    {{ __('Log out other devices') }}
                </x-ui.button>
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
