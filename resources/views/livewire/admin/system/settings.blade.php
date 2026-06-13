<div class="space-y-6">

    <x-ui.section-heading :title="__('Settings')" :subtitle="__('Each section saves on its own. Commission settings live under Finance.')" as="h1" />

    <div class="grid gap-6 xl:grid-cols-2">

        {{-- ── General ───────────────────────────────────────────────── --}}
        <x-ui.card class="p-4">
            <form wire:submit="saveGeneral" class="space-y-4">
                <h2 class="font-display text-lg font-semibold">{{ __('General') }}</h2>

                <x-ui.input :label="__('Site name')" wire:model="siteName" :error="$errors->first('siteName')" />

                <fieldset>
                    <legend class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Display currencies') }}</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($activeCurrencies as $currency)
                            <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-[13px] font-medium text-ink {{ $currency->is_base ? 'opacity-60' : '' }}">
                                <input type="checkbox" wire:model="displayCurrencies" value="{{ $currency->code }}" @disabled($currency->is_base) @checked($currency->is_base)
                                       class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                                {{ $currency->code }}
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-1.5 text-[13px] text-ink-faint">{{ __('Buyers can pick these in the storefront switcher. MYR is always included.') }}</p>
                    @error('displayCurrencies.*')<p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>@enderror
                </fieldset>

                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="saveGeneral">{{ __('Save general') }}</x-ui.button>
            </form>
        </x-ui.card>

        {{-- ── Order ─────────────────────────────────────────────────── --}}
        <x-ui.card class="p-4">
            <form wire:submit="saveOrder" class="space-y-4">
                <h2 class="font-display text-lg font-semibold">{{ __('Orders') }}</h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input :label="__('Return window (days)')" type="number" min="1" max="60" wire:model="returnWindowDays" :error="$errors->first('returnWindowDays')" />
                    <x-ui.input :label="__('Auto-complete after (days)')" type="number" min="1" max="60" wire:model="autoCompleteDays" :error="$errors->first('autoCompleteDays')" />
                    <x-ui.input :label="__('Unpaid order expiry (minutes)')" type="number" min="5" max="10080" wire:model="unpaidExpiryMinutes" :error="$errors->first('unpaidExpiryMinutes')" />
                    <x-ui.input :label="__('Minimum payout (RM)')" inputmode="decimal" placeholder="50.00" wire:model="payoutMin" :error="$errors->first('payoutMin')" />
                </div>

                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="saveOrder">{{ __('Save orders') }}</x-ui.button>
            </form>
        </x-ui.card>

        {{-- ── COD ───────────────────────────────────────────────────── --}}
        <x-ui.card class="p-4">
            <form wire:submit="saveCod" class="space-y-4">
                <h2 class="font-display text-lg font-semibold">{{ __('Cash on delivery') }}</h2>

                <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-[13px] font-medium text-ink">
                    <input type="checkbox" wire:model="codEnabled" class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                    {{ __('COD enabled platform-wide') }}
                </label>

                <x-ui.input :label="__('Maximum COD order (RM)')" inputmode="decimal" placeholder="500.00" wire:model="codMaxOrder" :error="$errors->first('codMaxOrder')" class="max-w-xs" />

                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="saveCod">{{ __('Save COD') }}</x-ui.button>
            </form>
        </x-ui.card>

        {{-- ── Moderation ────────────────────────────────────────────── --}}
        <x-ui.card class="p-4">
            <form wire:submit="saveModeration" class="space-y-4">
                <h2 class="font-display text-lg font-semibold">{{ __('Moderation') }}</h2>

                <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-[13px] font-medium text-ink">
                    <input type="checkbox" wire:model="requireProductApproval" class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                    {{ __('New products need admin approval before going live') }}
                </label>

                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="saveModeration">{{ __('Save moderation') }}</x-ui.button>
            </form>
        </x-ui.card>

        {{-- ── Security (Turnstile + Google OAuth + SMS) ─────────────── --}}
        <x-ui.card class="p-4">
            <form wire:submit="saveSecurity" class="space-y-4">
                <h2 class="font-display text-lg font-semibold">{{ __('Security') }}</h2>

                <p class="text-[13px] font-medium text-ink-soft">{{ __('Cloudflare Turnstile') }}</p>
                <x-ui.input :label="__('Site key')" wire:model="turnstileSiteKey" :error="$errors->first('turnstileSiteKey')" />
                <x-ui.input :label="__('Secret key')" type="password" wire:model="turnstileSecret" autocomplete="new-password"
                            :placeholder="$turnstileSecretSet ? __('•••••••• (configured — leave blank to keep)') : __('Paste the secret key')"
                            :error="$errors->first('turnstileSecret')" />

                <p class="border-t border-line pt-4 text-[13px] font-medium text-ink-soft">{{ __('Google sign-in') }}</p>
                <x-ui.input :label="__('Client ID')" wire:model="googleClientId" :error="$errors->first('googleClientId')" />
                <x-ui.input :label="__('Client secret')" type="password" wire:model="googleClientSecret" autocomplete="new-password"
                            :placeholder="$googleClientSecretSet ? __('•••••••• (configured — leave blank to keep)') : __('Paste the client secret')"
                            :error="$errors->first('googleClientSecret')" />
                <p class="text-[13px] text-ink-faint">{{ __('The "Continue with Google" button appears on login and register once both values are set.') }}</p>

                <p class="border-t border-line pt-4 text-[13px] font-medium text-ink-soft">{{ __('SMS gateway') }}</p>
                <x-ui.input :label="__('Provider API key')" type="password" wire:model="smsProviderKey" autocomplete="new-password"
                            :placeholder="$smsProviderKeySet ? __('•••••••• (configured — leave blank to keep)') : __('Paste the provider API key')"
                            :error="$errors->first('smsProviderKey')" />
                <p class="text-[13px] text-ink-faint">{{ __('Stored for the production SMS driver. Locally, codes are written to the log instead of sent.') }}</p>

                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="saveSecurity">{{ __('Save security') }}</x-ui.button>
            </form>
        </x-ui.card>

        {{-- ── Tracking pixels ───────────────────────────────────────── --}}
        <x-ui.card class="p-4">
            <form wire:submit="saveTracking" class="space-y-4">
                <h2 class="font-display text-lg font-semibold">{{ __('Tracking') }}</h2>

                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.input :label="__('GA4 measurement ID')" placeholder="G-XXXXXXX" wire:model="ga4Id" :error="$errors->first('ga4Id')" />
                    <x-ui.input :label="__('Meta pixel ID')" wire:model="metaPixelId" :error="$errors->first('metaPixelId')" />
                    <x-ui.input :label="__('TikTok pixel ID')" wire:model="tiktokPixelId" :error="$errors->first('tiktokPixelId')" />
                </div>

                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="saveTracking">{{ __('Save tracking') }}</x-ui.button>
            </form>
        </x-ui.card>

        {{-- ── iPay88 ────────────────────────────────────────────────── --}}
        <x-ui.card class="p-4">
            <form wire:submit="saveIpay88" class="space-y-4">
                <h2 class="font-display text-lg font-semibold">{{ __('iPay88') }}</h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input :label="__('Merchant code')" wire:model="merchantCode" :error="$errors->first('merchantCode')" />
                    <x-ui.input :label="__('Merchant key')" type="password" wire:model="merchantKey" autocomplete="new-password"
                                :placeholder="$merchantKeySet ? __('•••••••• (configured — leave blank to keep)') : __('Paste the merchant key')"
                                :error="$errors->first('merchantKey')" />
                </div>

                <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-[13px] font-medium text-ink">
                    <input type="checkbox" wire:model="sandbox" class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                    {{ __('Sandbox mode') }}
                </label>

                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="saveIpay88">{{ __('Save iPay88') }}</x-ui.button>
                    <x-ui.button variant="secondary" wire:click="testIpay88Connection" wire:loading.attr="disabled" wire:target="testIpay88Connection">
                        {{ __('Test connection') }}
                    </x-ui.button>
                </div>
                <p class="text-[13px] text-ink-faint">{{ __('The test sends a dummy requery — a structured error back from iPay88 means the connection works.') }}</p>
            </form>
        </x-ui.card>
    </div>
</div>
