<div class="mx-auto w-full max-w-2xl px-4 py-12 sm:py-16">
    <h1 class="font-display text-[28px] font-bold leading-tight">{{ __('Become a seller') }}</h1>
    <p class="mt-1 text-sm text-ink-soft">{{ __('Tell us about your shop. Applications are reviewed within 2–3 business days.') }}</p>

    <form wire:submit="submit" class="mt-6 space-y-4">
        {{-- ===== Shop details ===== --}}
        <x-ui.card class="p-6">
            <h2 class="font-display text-lg font-semibold">{{ __('Shop details') }}</h2>

            <div class="mt-4 space-y-4">
                <div>
                    <x-ui.input
                        :label="__('Shop name')"
                        name="name"
                        wire:model.live.debounce.400ms="name"
                        required
                        autofocus
                        maxlength="60"
                        :error="$errors->first('name')"
                    />
                    @if ($this->slugPreview !== '')
                        <p class="mt-1.5 text-[13px] {{ $this->slugTaken ? 'text-danger' : 'text-ink-faint' }}">
                            {{ __('Shop URL:') }} <span class="font-mono">{{ url('/s') }}/{{ $this->slugPreview }}</span>
                            @if ($this->slugTaken)
                                — {{ __('this name is already taken.') }}
                            @endif
                        </p>
                    @endif
                </div>

                <div>
                    <label for="description" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Description') }}</label>
                    <textarea
                        id="description"
                        wire:model="description"
                        rows="4"
                        required
                        maxlength="2000"
                        placeholder="{{ __('What do you sell? What makes your shop worth following?') }}"
                        class="block w-full rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('description') ? 'border-danger' : 'border-line-strong' }}"
                    ></textarea>
                    @error('description')
                        <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="state" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('State') }}</label>
                    <select
                        id="state"
                        wire:model="state"
                        required
                        class="block min-h-11 w-full rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('state') ? 'border-danger' : 'border-line-strong' }}"
                    >
                        <option value="">{{ __('Select a state') }}</option>
                        @foreach ($states as $stateOption)
                            <option value="{{ $stateOption }}">{{ $stateOption }}</option>
                        @endforeach
                    </select>
                    @error('state')
                        <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="flex min-h-11 cursor-pointer items-center gap-2.5 py-2 text-sm text-ink">
                        <input type="checkbox" wire:model.live="sstRegistered" class="size-4 shrink-0 rounded accent-emerald">
                        {{ __('My business is SST-registered') }}
                    </label>
                    @if ($sstRegistered)
                        <x-ui.input
                            :label="__('SST registration number')"
                            name="sstNumber"
                            wire:model="sstNumber"
                            maxlength="30"
                            :error="$errors->first('sstNumber')"
                        />
                    @endif
                </div>
            </div>
        </x-ui.card>

        {{-- ===== Bank details ===== --}}
        <x-ui.card class="p-6">
            <h2 class="font-display text-lg font-semibold">{{ __('Bank details') }}</h2>
            <p class="mt-1 text-[13px] text-ink-soft">{{ __('Payouts for completed orders are transferred to this account.') }}</p>

            <div class="mt-4 space-y-4">
                <div>
                    <label for="bankName" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Bank') }}</label>
                    <select
                        id="bankName"
                        wire:model="bankName"
                        required
                        class="block min-h-11 w-full rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('bankName') ? 'border-danger' : 'border-line-strong' }}"
                    >
                        <option value="">{{ __('Select a bank') }}</option>
                        @foreach ($banks as $bank)
                            <option value="{{ $bank }}">{{ $bank }}</option>
                        @endforeach
                    </select>
                    @error('bankName')
                        <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <x-ui.input
                    :label="__('Account holder name')"
                    name="accountName"
                    wire:model="accountName"
                    required
                    maxlength="120"
                    :error="$errors->first('accountName')"
                />

                <x-ui.input
                    :label="__('Account number')"
                    name="accountNumber"
                    wire:model="accountNumber"
                    inputmode="numeric"
                    required
                    class="font-mono"
                    :error="$errors->first('accountNumber')"
                    :hint="$errors->first('accountNumber') ? null : __('8–17 digits, no spaces or dashes.')"
                />
            </div>
        </x-ui.card>

        {{-- ===== Documents ===== --}}
        <x-ui.card class="p-6">
            <h2 class="font-display text-lg font-semibold">{{ __('Documents') }}</h2>
            <p class="mt-1 text-[13px] text-ink-soft">{{ __('PDF, JPG or PNG, up to 4MB each.') }}</p>

            <div class="mt-4 space-y-4">
                <div>
                    <label for="ssmFile" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('SSM certificate') }}</label>
                    <input
                        type="file"
                        id="ssmFile"
                        wire:model="ssmFile"
                        accept=".pdf,.jpg,.jpeg,.png"
                        required
                        class="block w-full text-sm text-ink-soft file:mr-3 file:min-h-11 file:cursor-pointer file:rounded-lg file:border file:border-ink file:bg-surface file:px-4 file:py-2 file:text-sm file:font-semibold file:text-ink hover:file:bg-paper"
                    >
                    <div wire:loading wire:target="ssmFile" class="mt-1.5 text-[13px] text-ink-soft">{{ __('Uploading…') }}</div>
                    @error('ssmFile')
                        <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="icFile" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('IC copy (owner)') }}</label>
                    <input
                        type="file"
                        id="icFile"
                        wire:model="icFile"
                        accept=".pdf,.jpg,.jpeg,.png"
                        required
                        class="block w-full text-sm text-ink-soft file:mr-3 file:min-h-11 file:cursor-pointer file:rounded-lg file:border file:border-ink file:bg-surface file:px-4 file:py-2 file:text-sm file:font-semibold file:text-ink hover:file:bg-paper"
                    >
                    <div wire:loading wire:target="icFile" class="mt-1.5 text-[13px] text-ink-soft">{{ __('Uploading…') }}</div>
                    @error('icFile')
                        <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </x-ui.card>

        {{-- ===== Confirm + submit ===== --}}
        <x-ui.card class="p-6">
            <label class="flex cursor-pointer items-start gap-2.5 text-[13px] leading-snug text-ink-soft">
                <input type="checkbox" wire:model="confirm" class="mt-0.5 size-4 shrink-0 rounded accent-emerald" required>
                <span>{{ __('I confirm the information and documents I provided are accurate, and I agree to the marketplace seller terms.') }}</span>
            </label>
            @error('confirm')
                <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
            @enderror

            <div class="mt-4">
                <x-turnstile :error="$errors->first('turnstileToken')" />
            </div>

            <x-ui.button type="submit" class="mt-4 w-full" wire:loading.attr="disabled" wire:target="submit, ssmFile, icFile">
                <svg wire:loading wire:target="submit" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"/></svg>
                {{ __('Submit application') }}
            </x-ui.button>
        </x-ui.card>
    </form>
</div>
