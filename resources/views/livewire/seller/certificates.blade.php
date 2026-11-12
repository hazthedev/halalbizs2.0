@use('App\Enums\CertificateStatus')

<div class="space-y-4">

    <x-ui.section-heading
        as="h1"
        :title="__('Halal certificates')"
        :subtitle="__('Register the certificate a recognised body issued you, then tick the products its scope covers. We check it before the badge appears.')"
    />

    <x-ui.card class="space-y-3 p-5">
        <h2 class="font-display text-lg font-medium">{{ __('Find my WHIP certificates') }}</h2>
        <p class="text-sm text-ink-soft">{{ __('Use your verified company SSM number to link a certificate without retyping it.') }}</p>
        @if (blank(auth()->user()->store?->registration_no))
            <x-ui.input :label="__('Company SSM number')" name="whipRegistrationNo" wire:model="registrationNo" :error="$errors->first('registrationNo')" />
        @else
            <p class="text-sm">{{ __('Company SSM number') }}: {{ auth()->user()->store->registration_no }}</p>
        @endif
        <x-ui.button wire:click="findWhipCertificates" wire:loading.attr="disabled">{{ __('Find my certificates') }}</x-ui.button>
        @error('whip') <p role="alert" class="text-sm text-danger">{{ $message }}</p> @enderror
        @if ($whipSearched && empty($whipCertificates))
            <p class="text-sm text-ink-soft">{{ __('No WHIP certificates found for your company SSM number.') }}</p>
        @endif
        @foreach ($whipCertificates as $found)
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-3" wire:key="whip-{{ $found['number'] }}">
                <div class="min-w-0">
                    <p class="break-words font-mono text-sm font-medium">{{ $found['number'] }}</p>
                    <p class="text-sm text-ink-soft">{{ $found['scope'] ?: __('Scope not supplied') }}</p>
                    <p class="text-sm text-ink-soft">{{ __(ucfirst(str_replace('_', ' ', $found['status']))) }} · {{ __('Valid until') }} {{ $found['expiry'] ?: __('Not supplied') }}</p>
                </div>
                @if ($found['can_link'])
                    <x-ui.button variant="secondary" wire:click="linkWhipCertificate({{ Illuminate\Support\Js::from($found['number']) }})" wire:loading.attr="disabled">{{ __('Link certificate') }}</x-ui.button>
                @endif
            </div>
        @endforeach
    </x-ui.card>

    @if ($certificates->contains('source', 'whip'))
        <x-ui.card class="space-y-3 p-5">
            <h2 class="font-display text-lg font-medium">{{ __('Import certified products') }}</h2>
            <p class="text-sm text-ink-soft">{{ __('WHIP products are imported as drafts. Review the details, add photos and prices, then publish each product yourself.') }}</p>
            <label for="importCategoryId" class="block text-sm font-medium">{{ __('Category for imported drafts') }}</label>
            <select id="importCategoryId" wire:model="importCategoryId" class="block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface p-3 text-sm sm:max-w-sm">
                <option value="">{{ __('Choose a category') }}</option>
                @foreach ($importCategories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
            @error('importCategoryId') <p role="alert" class="text-sm text-danger">{{ $message }}</p> @enderror
            <a href="{{ route('seller.products.index') }}" wire:navigate class="inline-flex min-h-11 items-center text-sm text-emerald underline">{{ __('Review my product drafts') }}</a>
        </x-ui.card>
    @endif

    @if (! $creating && $editing === null)
        <div class="flex flex-wrap gap-3">
            <x-ui.button wire:click="startCreate">{{ __('Register a certificate') }}</x-ui.button>
            @if ($certificates->isEmpty() && config('services.whip.apply_url'))
                <a href="{{ config('services.whip.apply_url') }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] border border-line-strong bg-surface px-4 py-2.5 text-sm font-medium text-ink hover:bg-paper">{{ __('Get certified') }}</a>
            @endif
        </div>
    @endif

    {{-- ── Existing certificates ─────────────────────────────────────────── --}}
    @if ($certificates->isEmpty() && ! $creating)
        <x-ui.card class="p-6">
            <x-ui.empty-state
                :title="__('No certificates yet')"
                :message="__('Buyers filter by certifying body and look for the badge on the product page. Register yours to appear there.')"
            />
        </x-ui.card>
    @else
        <div class="space-y-3">
            @foreach ($certificates as $certificate)
                <x-ui.card class="p-4" wire:key="cert-{{ $certificate->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-mono text-sm font-medium">{{ $certificate->number }}</p>
                                <x-ui.badge :variant="$certificate->status->variant()">{{ $certificate->status->label() }}</x-ui.badge>

                                @if ($certificate->status === CertificateStatus::Approved && $certificate->isExpiringSoon())
                                    <x-ui.badge variant="warn">
                                        {{ __(':days days to expiry', ['days' => $certificate->daysRemaining()]) }}
                                    </x-ui.badge>
                                @endif
                            </div>
                            <p class="mt-1 text-[13px] text-ink-soft">
                                {{ $certificate->issuing_body }} ·
                                {{ $certificate->valid_from->format('d M Y') }} – {{ $certificate->valid_to->format('d M Y') }} ·
                                {{ trans_choice('{0} no products|{1} 1 product|[2,*] :count products', $certificate->products->count(), ['count' => $certificate->products->count()]) }}
                            </p>
                            @if ($certificate->unmatchedProducts()->isNotEmpty())
                                <p class="mt-1 text-[13px] text-ink-soft">{{ __('Not on your WHIP certificate, waiting for admin: :names', ['names' => $certificate->unmatchedProducts()->pluck('name')->implode(', ')]) }}</p>
                            @endif

                            @if ($certificate->status === CertificateStatus::Rejected && $certificate->review_note)
                                <p class="mt-2 max-w-prose rounded-[var(--radius-control)] bg-danger-tint px-3 py-2 text-[13px] text-danger">
                                    {{ $certificate->review_note }}
                                </p>
                            @endif
                        </div>

                        @if ($editing !== $certificate->id)
                            <div class="flex flex-wrap gap-2">
                            @if ($certificate->source === 'whip' && $certificate->status === CertificateStatus::Approved)
                                <x-ui.button variant="secondary" wire:click="importWhipProducts({{ $certificate->id }})" wire:loading.attr="disabled">{{ __('Import product drafts') }}</x-ui.button>
                            @endif
                            <x-ui.button variant="ghost" wire:click="edit({{ $certificate->id }})">
                                {{ $certificate->status === CertificateStatus::Rejected ? __('Fix and resubmit') : __('Renew or edit') }}
                            </x-ui.button>
                            </div>
                        @endif
                    </div>
                </x-ui.card>
            @endforeach
        </div>
    @endif

    {{-- ── Submission / renewal form ─────────────────────────────────────── --}}
    @if ($creating || $editing !== null)
        <x-ui.card class="p-5">
            <h2 class="font-display text-lg font-medium">
                {{ $editing !== null ? __('Renew or correct this certificate') : __('Register a certificate') }}
            </h2>

            @if ($editing !== null)
                <p class="mt-1 max-w-prose text-[13px] text-ink-soft">
                    {{ __('A renewal updates this same record, so its number and public history stay intact. It goes back for review once you send it, and the halal badge pauses until we approve it — your products stay on sale throughout.') }}
                </p>
            @endif

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <x-ui.input :label="__('Certificate number')" name="number" wire:model="number"
                            :error="$errors->first('number')"
                            :hint="__('Exactly as printed, e.g. MY-JKM-1234-100.')" />

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
                <x-ui.button variant="ghost" wire:click="cancelEdit">{{ __('Cancel') }}</x-ui.button>
            </div>
        </x-ui.card>
    @endif
</div>
