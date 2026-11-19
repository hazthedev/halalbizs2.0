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

    @if ($hasWhip)
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

    <div class="flex flex-wrap gap-3">
        <x-ui.button icon="plus" :href="route('seller.certificates.create')" wire:navigate>{{ __('Register a certificate') }}</x-ui.button>
        @if (! $hasAny && config('services.whip.apply_url'))
            <a href="{{ config('services.whip.apply_url') }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] border border-line-strong bg-surface px-4 py-2.5 text-sm font-medium text-ink hover:bg-paper">{{ __('Get certified') }}</a>
        @endif
    </div>

    @if (! $hasAny)
        <x-ui.card class="p-6">
            <x-ui.empty-state
                :title="__('No certificates yet')"
                :message="__('Buyers filter by certifying body and look for the badge on the product page. Register yours to appear there.')"
            />
        </x-ui.card>
    @else
        @php $fieldClass = 'block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald'; @endphp
        <x-ui.card class="p-3">
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-6">
                <div class="min-w-0 lg:col-span-2">
                    <label for="cert-search" class="sr-only">{{ __('Search certificates') }}</label>
                    <input id="cert-search" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search number or issuing body') }}" class="{{ $fieldClass }} font-mono placeholder:font-sans placeholder:text-ink-faint">
                </div>
                <div>
                    <label for="cert-status" class="sr-only">{{ __('Status') }}</label>
                    <select id="cert-status" wire:model.live="status" class="{{ $fieldClass }}">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach (CertificateStatus::cases() as $case)
                            <option value="{{ $case->value }}">{{ $case->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="cert-body" class="sr-only">{{ __('Issuing body') }}</label>
                    <select id="cert-body" wire:model.live="body" class="{{ $fieldClass }}">
                        <option value="">{{ __('All issuing bodies') }}</option>
                        @foreach ($bodies as $code => $meta)
                            <option value="{{ $code }}">{{ $code }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="cert-source" class="sr-only">{{ __('Source') }}</label>
                    <select id="cert-source" wire:model.live="source" class="{{ $fieldClass }}">
                        <option value="">{{ __('All sources') }}</option>
                        <option value="whip">{{ __('WHIP') }}</option>
                        <option value="manual">{{ __('Manual') }}</option>
                    </select>
                </div>
                <label class="inline-flex min-h-11 items-center gap-2 text-[13px] text-ink">
                    <input type="checkbox" wire:model.live="expiring" class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                    {{ __('Expiring soon') }}
                </label>
                <div class="flex min-w-0 items-center gap-2 lg:col-span-3">
                    <label for="cert-from" class="sr-only">{{ __('Expires from') }}</label>
                    <input id="cert-from" type="date" wire:model.live="validFrom" class="{{ $fieldClass }} min-w-0">
                    <span class="text-[13px] text-ink-faint" aria-hidden="true">–</span>
                    <label for="cert-to" class="sr-only">{{ __('Expires to') }}</label>
                    <input id="cert-to" type="date" wire:model.live="validTo" class="{{ $fieldClass }} min-w-0">
                </div>
            </div>
            @if ($search !== '' || $status !== '' || $body !== '' || $source !== '' || $expiring || $validFrom !== '' || $validTo !== '')
                <button type="button" wire:click="resetFilters" class="mt-2 inline-flex min-h-11 items-center text-[13px] font-medium text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Clear filters') }}</button>
            @endif
        </x-ui.card>

        <x-ui.table.per-page />

        <x-ui.card class="overflow-x-auto">
            <x-ui.table-skeleton wire:loading wire:target="search, status, body, source, expiring, validFrom, validTo, resetFilters, sortBy, perPage" />
            <div wire:loading.remove wire:target="search, status, body, source, expiring, validFrom, validTo, resetFilters, sortBy, perPage">
            @if ($certificates->isEmpty())
                <x-ui.empty-state :title="__('No certificates match')" :message="__('Adjust the filters or clear them to see every certificate.')" />
            @else
                <table class="w-full min-w-[860px] text-[13px]">
                    <thead class="sticky top-0 z-10 bg-surface">
                        <tr class="border-b border-line text-left text-ink-soft">
                            <x-ui.table.sortable field="number" :sort="$sort" :direction="$direction">{{ __('Number') }}</x-ui.table.sortable>
                            <x-ui.table.sortable field="status" :sort="$sort" :direction="$direction">{{ __('Status') }}</x-ui.table.sortable>
                            <th scope="col" class="px-3 py-6.5 font-medium">{{ __('Issuing body') }}</th>
                            <x-ui.table.sortable field="valid_to" :sort="$sort" :direction="$direction">{{ __('Valid') }}</x-ui.table.sortable>
                            <x-ui.table.sortable field="products" :sort="$sort" :direction="$direction" class="px-3 py-6.5 text-right font-medium">{{ __('Products') }}</x-ui.table.sortable>
                            <th scope="col" class="px-3 py-6.5 font-medium">{{ __('Source') }}</th>
                            <th scope="col" class="px-3 py-6.5 text-right font-medium">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($certificates as $certificate)
                            <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="cert-{{ $certificate->id }}">
                                <td class="px-3 py-2">
                                    <span class="break-words font-mono font-medium">{{ $certificate->number }}</span>
                                    @if (! empty($certificate->whip_unmatched_product_ids) && $certificate->unmatchedProducts()->isNotEmpty())
                                        <p class="mt-1 text-ink-soft">{{ __('Not on your WHIP certificate, waiting for admin: :names', ['names' => $certificate->unmatchedProducts()->pluck('name')->implode(', ')]) }}</p>
                                    @endif
                                    @if ($certificate->status === CertificateStatus::Rejected && $certificate->review_note)
                                        <p class="mt-2 max-w-prose rounded-[var(--radius-control)] bg-danger-tint px-3 py-2 text-danger">{{ $certificate->review_note }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-2">
                                    <x-ui.badge :variant="$certificate->status->variant()">{{ $certificate->status->label() }}</x-ui.badge>
                                    @if ($certificate->status === CertificateStatus::Approved && $certificate->isExpiringSoon())
                                        <x-ui.badge variant="warn">{{ __(':days days to expiry', ['days' => $certificate->daysRemaining()]) }}</x-ui.badge>
                                    @endif
                                </td>
                                <td class="px-3 py-2">{{ $certificate->issuing_body }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-ink-soft">{{ $certificate->valid_from->format('d M Y') }} – {{ $certificate->valid_to->format('d M Y') }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $certificate->products_count }}</td>
                                <td class="px-3 py-2">{{ $certificate->source === 'whip' ? __('WHIP') : __('Manual') }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        @if ($certificate->source === 'whip' && $certificate->status === CertificateStatus::Approved)
                                            <x-ui.button variant="secondary" wire:click="importWhipProducts({{ $certificate->id }})">{{ __('Import product drafts') }}</x-ui.button>
                                        @endif
                                        <x-ui.button variant="ghost" :href="route('seller.certificates.edit', $certificate)" wire:navigate>
                                            {{ $certificate->status === CertificateStatus::Rejected ? __('Fix and resubmit') : __('Renew or edit') }}
                                        </x-ui.button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            </div>
        </x-ui.card>

        <x-ui.table.footer :rows="$certificates" />
    @endif
</div>
