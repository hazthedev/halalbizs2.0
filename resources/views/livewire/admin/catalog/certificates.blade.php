@use('App\Enums\CertificateStatus')

<div class="space-y-4">

    <x-ui.section-heading
        as="h1"
        :title="__('Halal certificates')"
        :subtitle="__('Sellers submit the certificate a recognised body issued them. Nothing badges a product until it is approved here.')"
    />

    {{-- Filter --}}
    <div class="flex flex-wrap items-center gap-2">
        <div class="inline-flex rounded-[var(--radius-control)] border border-line bg-surface p-0.5"
             role="group" aria-label="{{ __('Filter by review state') }}">
            @foreach (['pending' => __('Awaiting review'), 'approved' => __('Approved'), 'rejected' => __('Rejected'), 'all' => __('All')] as $key => $label)
                <button type="button"
                        wire:click="$set('filter', '{{ $key }}')"
                        wire:key="filter-{{ $key }}"
                        aria-pressed="{{ $filter === $key ? 'true' : 'false' }}"
                        class="min-h-10 rounded-md px-3 text-[13px] font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $filter === $key ? 'bg-emerald-night text-on-dark' : 'text-ink-soft hover:text-ink' }}">
                    {{ $label }}
                    @if ($key === 'pending' && $pendingCount > 0)
                        <span class="ml-1 tabular-nums">{{ $pendingCount }}</span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    @if ($certificates->isEmpty())
        <x-ui.card class="p-6">
            <x-ui.empty-state
                :title="__('Nothing here')"
                :message="__('Certificates submitted by sellers land in this queue, oldest first.')"
            />
        </x-ui.card>
    @else
        <div class="space-y-3">
            @foreach ($certificates as $certificate)
                <x-ui.card class="p-4" wire:key="admin-cert-{{ $certificate->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-mono text-sm font-medium">{{ $certificate->number }}</p>
                                <x-ui.badge :variant="$certificate->status->variant()">{{ $certificate->status->label() }}</x-ui.badge>
                            </div>
                            <p class="mt-1 text-[13px] text-ink-soft">
                                {{ $certificate->store?->name }} ·
                                {{ $certificate->issuing_body }} ·
                                {{ $certificate->valid_from->format('d M Y') }} – {{ $certificate->valid_to->format('d M Y') }} ·
                                {{ trans_choice('{0} no products|{1} 1 product|[2,*] :count products', $certificate->products->count(), ['count' => $certificate->products->count()]) }}
                            </p>
                            @if ($certificate->submitted_at)
                                <p class="mt-0.5 text-[11px] text-ink-faint">
                                    {{ __('Submitted :when', ['when' => $certificate->submitted_at->diffForHumans()]) }}
                                </p>
                            @endif
                        </div>

                        <x-ui.button variant="ghost" wire:click="review({{ $certificate->id }})">
                            {{ $reviewing === $certificate->id ? __('Close') : __('Review') }}
                        </x-ui.button>
                    </div>

                    {{-- ── Review panel ──────────────────────────────────── --}}
                    @if ($reviewing === $certificate->id)
                        <div class="mt-4 border-t border-line pt-4">
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
                                    {{ __('Approved :when.', ['when' => $certificate->reviewed_at?->format('d M Y') ?? '—']) }}
                                </p>
                            @endif
                        </div>
                    @endif
                </x-ui.card>
            @endforeach
        </div>

        <div>{{ $certificates->links() }}</div>
    @endif
</div>
