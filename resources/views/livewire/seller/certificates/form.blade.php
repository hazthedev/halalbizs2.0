<div class="space-y-4">
    <x-ui.section-heading as="h1" :title="$editing !== null ? __('Renew or correct this certificate') : __('Register a certificate')" />

    <x-ui.card class="p-5">
        @if ($editing !== null)
            <p class="mt-1 max-w-prose text-[13px] text-ink-soft">
                {{ __('A renewal updates this same record, so its number and public history stay intact. It goes back for review once you send it, and the halal badge pauses until we approve it — your products stay on sale throughout.') }}
            </p>
        @endif

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <x-ui.input :label="__('Certificate number')" name="number" wire:model="number"
                        :error="$errors->first('number')"
                        :hint="__('Exactly as printed, e.g. MY-JKM-1234-100.')" />

            @if ($retiredBody)
            {{-- HB-G141: a body no longer accepted stays on the certificate that names it, read-only. --}}
            <div>
                <p class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Issuing body') }}</p>
                <p class="block w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 py-2.5 text-sm text-ink">{{ $issuingBody }} — {{ $retiredBody['name'] }}</p>
                <p class="mt-1 text-[13px] text-ink-soft">{{ __('No longer accepted for new certificates.') }}</p>
            </div>
            @else
            <div>
                <label for="issuingBody" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Issuing body') }}</label>
                <select id="issuingBody" wire:model="issuingBody"
                        class="block w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 py-2.5 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald">
                    <option value="">{{ __('Choose a body') }}</option>
                    @foreach ($bodies as $code => $meta)
                        <option value="{{ $code }}">{{ $code }} — {{ $meta['name'] }}</option>
                    @endforeach
                </select>
                @error('issuingBody') <p class="mt-1 text-[13px] text-danger">{{ $message }}</p> @enderror
            </div>
            @endif

            <x-ui.input :label="__('Certificate holder')" name="holderName" wire:model="holderName"
                        :error="$errors->first('holderName')"
                        :hint="__('The legal name on the certificate, which may differ from your shop name.')" />

            <div>
                <label for="scheme" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Standard') }}</label>
                <input id="scheme" list="halal-schemes" wire:model="scheme" placeholder="MS 1500:2019"
                       class="block w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 py-2.5 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald">
                {{-- Datalist, not a select: these cover almost every certificate
                     we will see, but the standard is printed on the document and
                     a seller must be able to enter what theirs actually says. --}}
                <datalist id="halal-schemes">
                    <option value="MS 1500:2019">{{ __('Halal food — general requirements') }}</option>
                    <option value="MS 2400">{{ __('Halal supply chain management') }}</option>
                    <option value="MS 2200">{{ __('Islamic consumer goods') }}</option>
                    <option value="MS 2424">{{ __('Halal pharmaceuticals') }}</option>
                </datalist>
                @error('scheme') <p class="mt-1 text-[13px] text-danger">{{ $message }}</p> @enderror
            </div>

            <x-ui.input :label="__('Company registration number')" name="registrationNo" wire:model="registrationNo"
                        :error="$errors->first('registrationNo')"
                        :disabled="filled(auth()->user()->store?->registration_no)"
                        :hint="__('As on your SSM; locked once saved. After we confirm it once, certificates WHIP holds under this number are verified at once.')" />

            <x-ui.input :label="__('Issued on')" name="validFrom" type="date" wire:model="validFrom"
                        :error="$errors->first('validFrom')" />

            <x-ui.input :label="__('Expires on')" name="validTo" type="date" wire:model="validTo"
                        :error="$errors->first('validTo')"
                        :hint="__('Terms usually run 1–3 years. Renew at least three months before this date.')" />

            <x-ui.input :label="__('Facility')" name="facility" wire:model="facility"
                        :error="$errors->first('facility')"
                        :hint="__('Optional — the plant or premise named on the certificate.')" />

            <x-ui.input :label="__('Scope note')" name="scopeNote" wire:model="scopeNote"
                        :error="$errors->first('scopeNote')"
                        :hint="__('Optional — what the certificate covers, in your words.')" />
        </div>

        <div class="mt-4 space-y-2">
            <label class="flex items-center gap-2 text-[13px]">
                <input type="checkbox" wire:model="dedicatedFacility" class="rounded border-line-strong text-emerald focus-visible:ring-emerald">
                {{ __('Produced in a dedicated halal facility') }}
            </label>
            <label class="flex items-center gap-2 text-[13px]">
                <input type="checkbox" wire:model="exportPaperwork" class="rounded border-line-strong text-emerald focus-visible:ring-emerald">
                {{ __('Export paperwork available') }}
            </label>
        </div>

        <div class="mt-4">
            <label for="document" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Certificate document') }}</label>
            <input id="document" type="file" wire:model="document" accept=".pdf,.jpg,.jpeg,.png"
                   class="block w-full text-[13px] text-ink-soft file:mr-3 file:rounded-[var(--radius-control)] file:border-0 file:bg-paper file:px-3 file:py-2 file:text-[13px] file:font-medium file:text-ink">
            <p class="mt-1 text-[13px] text-ink-soft">
                {{ __('PDF or photo, up to 4 MB. Only our reviewers see it — it is never shown on your shop or the public register.') }}
            </p>
            <div wire:loading wire:target="document" class="mt-1 text-[13px] text-ink-soft">{{ __('Uploading…') }}</div>
            @error('document') <p class="mt-1 text-[13px] text-danger">{{ $message }}</p> @enderror
        </div>

        {{-- ── SKU binding ───────────────────────────────────────────────
             The certificate binds to the PRODUCTS its scope names, not to
             the shop: a badge on the shop tells a buyer nothing about the
             item in their basket. --}}
        <div class="mt-5">
            <p class="text-[13px] font-medium text-ink">{{ __('Products this certificate covers') }}</p>
            <p class="mt-1 text-[13px] text-ink-soft">
                {{ __('Only the products named in the scope. Anything you untick loses its badge when this is approved.') }}
            </p>

            @if ($products->isEmpty())
                <p class="mt-2 text-[13px] text-ink-soft">{{ __('You have no products yet.') }}</p>
            @else
                <div class="mt-2 max-h-64 space-y-1 overflow-y-auto rounded-[var(--radius-control)] border border-line p-3"
                     tabindex="0" role="group" aria-label="{{ __('Products this certificate covers') }}">
                    @foreach ($products as $product)
                        <label class="flex items-center gap-2 text-[13px]" wire:key="cover-{{ $product->id }}">
                            <input type="checkbox" value="{{ $product->id }}" wire:model="covered"
                                   class="rounded border-line-strong text-emerald focus-visible:ring-emerald">
                            <span>{{ $product->getTranslation('name', app()->getLocale()) }}</span>
                            @if ($product->halal_certificate_id && $product->halal_certificate_id !== $editing)
                                <span class="text-[11px] text-ink-faint">{{ __('(covered by another certificate)') }}</span>
                            @endif
                        </label>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="mt-5 flex flex-wrap gap-2">
            <x-ui.button wire:click="save" wire:loading.attr="disabled" wire:target="save, document">
                {{ __('Send for review') }}
            </x-ui.button>
            <x-ui.button variant="ghost" :href="route('seller.certificates')" wire:navigate>{{ __('Cancel') }}</x-ui.button>
        </div>
    </x-ui.card>
</div>
