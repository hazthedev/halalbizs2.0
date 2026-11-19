@use('App\Enums\CertificateStatus')

<div class="space-y-4">

    <x-ui.section-heading
        as="h1"
        :title="__('Halal certificates')"
        :subtitle="__('Sellers submit the certificate a recognised body issued them. Nothing badges a product until it is approved here.')"
    />

    {{-- Review state --}}
    <nav class="flex gap-1 overflow-x-auto border-b border-line" aria-label="{{ __('Filter by review state') }}">
        @foreach (['pending' => __('Awaiting review'), 'approved' => __('Approved'), 'rejected' => __('Rejected'), 'all' => __('All')] as $key => $label)
            <x-ui.tab wire:click="$set('filter', '{{ $key }}')" wire:key="filter-{{ $key }}" :active="$filter === $key" :count="$key === 'pending' ? $pendingCount : 0">{{ $label }}</x-ui.tab>
        @endforeach
    </nav>
    @php $fieldClass = 'block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald'; @endphp
    <x-ui.card class="p-3">
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-6">
            <div class="min-w-0 lg:col-span-2">
                <label for="acert-search" class="sr-only">{{ __('Search certificates') }}</label>
                <input id="acert-search" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search number or store') }}" class="{{ $fieldClass }} font-mono placeholder:font-sans placeholder:text-ink-faint">
            </div>
            <div>
                <label for="acert-body" class="sr-only">{{ __('Issuing body') }}</label>
                <select id="acert-body" wire:model.live="body" class="{{ $fieldClass }}">
                    <option value="">{{ __('All issuing bodies') }}</option>
                    @foreach ($bodies as $code => $meta)
                        <option value="{{ $code }}">{{ $code }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="acert-store" class="sr-only">{{ __('Store') }}</label>
                <select id="acert-store" wire:model.live="store" class="{{ $fieldClass }}">
                    <option value="">{{ __('All stores') }}</option>
                    @foreach ($stores as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <label class="inline-flex min-h-11 items-center gap-2 text-[13px] text-ink">
                <input type="checkbox" wire:model.live="expiring" class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                {{ __('Expiring soon') }}
            </label>
            <div class="flex min-w-0 items-center gap-2 lg:col-span-2">
                <label for="acert-from" class="sr-only">{{ __('Submitted from') }}</label>
                <input id="acert-from" type="date" wire:model.live="dateFrom" class="{{ $fieldClass }} min-w-0">
                <span class="text-[13px] text-ink-faint" aria-hidden="true">–</span>
                <label for="acert-to" class="sr-only">{{ __('Submitted to') }}</label>
                <input id="acert-to" type="date" wire:model.live="dateTo" class="{{ $fieldClass }} min-w-0">
            </div>
        </div>
        @if ($search !== '' || $body !== '' || $store !== '' || $expiring || $dateFrom !== '' || $dateTo !== '')
            <button type="button" wire:click="resetFilters" class="mt-2 inline-flex min-h-11 items-center text-[13px] font-medium text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Clear filters') }}</button>
        @endif
    </x-ui.card>

    <x-ui.table.per-page />

    <x-ui.card class="overflow-x-auto">
        <x-ui.table-skeleton wire:loading wire:target="search, filter, body, store, expiring, dateFrom, dateTo, resetFilters, sortBy, perPage" />
        <div wire:loading.remove wire:target="search, filter, body, store, expiring, dateFrom, dateTo, resetFilters, sortBy, perPage">
        @if ($certificates->isEmpty())
            <x-ui.empty-state
                :title="__('Nothing here')"
                :message="__('Certificates submitted by sellers land in this queue, oldest first.')"
            />
        @else
            <table class="w-full min-w-[900px] text-[13px]">
                <thead class="sticky top-0 z-10 bg-surface">
                    <tr class="border-b border-line text-left text-ink-soft">
                        <x-ui.table.sortable field="number" :sort="$sort" :direction="$direction">{{ __('Number') }}</x-ui.table.sortable>
                        <th scope="col" class="px-3 py-5 font-medium">{{ __('Status') }}</th>
                        <x-ui.table.sortable field="store" :sort="$sort" :direction="$direction">{{ __('Store') }}</x-ui.table.sortable>
                        <th scope="col" class="px-3 py-5 font-medium">{{ __('Issuing body') }}</th>
                        <x-ui.table.sortable field="valid_to" :sort="$sort" :direction="$direction">{{ __('Valid') }}</x-ui.table.sortable>
                        <th scope="col" class="px-3 py-5 text-right font-medium">{{ __('Products') }}</th>
                        <x-ui.table.sortable field="submitted" default="asc" :sort="$sort" :direction="$direction">{{ __('Submitted') }}</x-ui.table.sortable>
                        <th scope="col" class="px-3 py-5 text-right font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                @foreach ($certificates as $certificate)
                    <tbody wire:key="admin-cert-{{ $certificate->id }}">
                        <tr class="border-b border-line hover:bg-paper">
                            <td class="px-3 py-2 font-mono font-medium">{{ $certificate->number }}</td>
                            <td class="px-3 py-2"><x-ui.badge :variant="$certificate->status->variant()">{{ $certificate->status->label() }}</x-ui.badge></td>
                            <td class="px-3 py-2">{{ $certificate->store?->name }}</td>
                            <td class="px-3 py-2">{{ $certificate->issuing_body }}</td>
                            <td class="px-3 py-2 whitespace-nowrap text-ink-soft">{{ $certificate->valid_from->format('d M Y') }} – {{ $certificate->valid_to->format('d M Y') }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $certificate->products_count }}</td>
                            <td class="px-3 py-2 whitespace-nowrap text-ink-soft">{{ $certificate->submitted_at?->diffForHumans() ?? '—' }}</td>
                            <td class="px-3 py-2 text-right">
                                <x-ui.button variant="ghost" wire:click="review({{ $certificate->id }})">
                                    {{ $reviewing === $certificate->id ? __('Close') : __('Review') }}
                                </x-ui.button>
                            </td>
                        </tr>
                        @if ($reviewing === $certificate->id)
                            <tr class="border-b border-line bg-paper/50">
                                <td colspan="8" class="px-4 pb-4">
<div class="mt-4 border-t border-line pt-4">
                            @if ($certificate->whipNote())
                                <p class="mb-3 text-[13px] font-medium text-ink">{{ $certificate->whipNote() }}</p>
                            @endif
                            @foreach ($certificate->unmatchedProducts() as $unmatched)
                                <div class="mb-2 flex items-center justify-between gap-3 text-[13px]" wire:key="unmatched-{{ $unmatched->id }}">
                                    <span>{{ __('Not on WHIP\'s certificate: :name', ['name' => $unmatched->name]) }}</span>
                                    <x-ui.button variant="ghost" wire:click="allowProduct({{ $certificate->id }}, {{ $unmatched->id }})" wire:loading.attr="disabled">{{ __('Allow') }}</x-ui.button>
                                </div>
                            @endforeach
                            <dl class="grid gap-3 text-[13px] sm:grid-cols-2">
                                <div>
                                    <dt class="text-ink-soft">{{ __('Holder') }}</dt>
                                    <dd class="font-medium">{{ $certificate->holder_name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-ink-soft">{{ __('Issuing body') }}</dt>
                                    <dd class="font-medium">{{ $certificate->issuing_body_name ?: $certificate->issuing_body }}</dd>
                                </div>
                                <div>
                                    <dt class="text-ink-soft">{{ __('Standard') }}</dt>
                                    <dd class="font-medium">{{ $certificate->scheme ?: '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-ink-soft">{{ __('Facility') }}</dt>
                                    <dd class="font-medium">{{ $certificate->facility ?: '—' }}</dd>
                                </div>
                                <div class="sm:col-span-2">
                                    <dt class="text-ink-soft">{{ __('Scope') }}</dt>
                                    <dd class="font-medium">{{ $certificate->scope_note ?: '—' }}</dd>
                                </div>
                            </dl>

                            {{-- The scan. Private disk — this route is the only way to read it. --}}
                            <div class="mt-4">
                                @if ($certificate->getFirstMedia('document'))
                                    <a href="{{ route('admin.catalog.certificates.document', $certificate) }}"
                                       target="_blank" rel="noopener"
                                       class="inline-flex min-h-11 items-center gap-2 text-[13px] font-medium text-emerald underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:ring-emerald">
                                        {{ __('Open the certificate document') }}
                                    </a>
                                @else
                                    <p class="text-[13px] text-warn">{{ __('No document was attached — reject and ask for one.') }}</p>
                                @endif
                            </div>

                            @if ($certificate->status !== CertificateStatus::Approved)
                                <div class="mt-4 flex flex-wrap items-start gap-2">
                                    <x-ui.button wire:click="approve({{ $certificate->id }})" wire:loading.attr="disabled">
                                        {{ __('Approve') }}
                                    </x-ui.button>
                                </div>

                                <div class="mt-4">
                                    <label for="reason-{{ $certificate->id }}" class="block text-[13px] font-medium text-ink">
                                        {{ __('Or reject, with a reason') }}
                                    </label>
                                    <p class="mt-0.5 text-[11px] text-ink-faint">{{ __('The seller sees this word for word.') }}</p>
                                    <textarea id="reason-{{ $certificate->id }}" wire:model="rejectionReason" rows="2"
                                              class="mt-1.5 block w-full max-w-xl rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 py-2.5 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald"></textarea>
                                    @error('rejectionReason') <p class="mt-1 text-[13px] text-danger">{{ $message }}</p> @enderror

                                    <x-ui.button variant="danger" class="mt-2" wire:click="reject({{ $certificate->id }})" wire:loading.attr="disabled">
                                        {{ __('Reject') }}
                                    </x-ui.button>
                                </div>
                            @else
                                <p class="mt-4 text-[13px] text-ink-soft">
                                    {{ __('Approved :when.', ['when' => $certificate->reviewed_at?->display()->format('d M Y') ?? '—']) }}
                                </p>
                            @endif
                        </div>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                @endforeach
            </table>
        @endif
        </div>
    </x-ui.card>

    <x-ui.table.footer :rows="$certificates" />
</div>
