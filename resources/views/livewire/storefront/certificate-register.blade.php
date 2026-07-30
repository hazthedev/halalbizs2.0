{{-- Certificate register — ported from the reference's verify screen.
     Search, then the record: issuing body, holder, scheme & scope, validity,
     audit trail, and how many SKUs the certificate actually covers. Every
     value is read from the record; nothing here is decorative. --}}
<div class="mx-auto max-w-[1400px] px-4 py-10 lg:px-12">

    <nav aria-label="{{ __('Breadcrumb') }}" class="font-mono text-[length:var(--text-tiny)] uppercase tracking-[var(--tracking-label)] text-ink-faint">
        <a href="{{ route('home') }}" wire:navigate class="hover:text-ink">{{ __('Home') }}</a>
        <span aria-hidden="true" class="mx-2">/</span>
        <span class="text-ink-soft">{{ __('Certificate register') }}</span>
    </nav>

    <h1 class="mt-5 font-display text-[length:var(--text-h1)] font-normal tracking-[var(--tracking-head)] text-ink-head">{{ __('Certificate register') }}</h1>

    <p class="mt-4 max-w-[62ch] text-[length:var(--text-base)] leading-relaxed text-ink-soft">
        {{ __('Enter any certificate number printed on a HalalBizs listing or invoice. Records are held against the issuing body and time-stamped at each check.') }}
    </p>

    {{-- Lookup --}}
    <form wire:submit="verify" class="mt-8 flex flex-wrap items-center gap-3">
        <label for="cert-number" class="sr-only">{{ __('Certificate number') }}</label>
        <input id="cert-number" type="text" wire:model="number"
               placeholder="{{ __('e.g. MY-JKM-2140-118') }}"
               class="h-12 min-w-0 flex-1 rounded-[var(--radius-pill)] border border-line bg-surface px-5 font-mono text-[length:var(--text-base)] text-ink placeholder:text-ink-faint focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald sm:max-w-2xl">
        <button type="submit"
                class="h-12 shrink-0 rounded-[var(--radius-pill)] bg-emerald px-8 text-[length:var(--text-base)] font-medium text-white transition-colors duration-(--dur-micro) hover:bg-emerald-deep">
            {{ __('Verify') }}
        </button>
    </form>

    @if ($searched && ! $certificate)
        {{-- Not found is a RESULT, not an error state: the honest answer is that
             this marketplace holds no record for that number. --}}
        <div class="mt-8 rounded-[var(--radius-card)] border border-line bg-surface p-6">
            <p class="font-mono text-[length:var(--text-tiny)] uppercase tracking-[var(--tracking-label-xl)] text-ink-faint">{{ __('No record') }}</p>
            <p class="mt-3 text-[length:var(--text-base)] text-ink-soft">
                {{ __('No certificate on this marketplace carries that number. Check the digits, or report the listing that showed it.') }}
            </p>
            <a href="{{ route('help.index') }}" wire:navigate class="mt-4 inline-block text-[length:var(--text-base)] text-emerald hover:text-emerald-deep">{{ __('Report a listing') }}</a>
        </div>
    @endif

    @if ($certificate)
        @php
            $valid = $certificate->isValid();
            $daysLeft = $certificate->daysRemaining();
        @endphp

        <div class="mt-8 overflow-hidden rounded-[var(--radius-card)] border {{ $valid ? 'border-emerald-tint-edge' : 'border-danger/30' }}">

            {{-- Verdict --}}
            <div class="flex flex-wrap items-start justify-between gap-4 px-6 py-5 {{ $valid ? 'bg-emerald-tint' : 'bg-danger-tint' }}">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 size-5 shrink-0 {{ $valid ? 'text-emerald' : 'text-danger' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $valid ? 'm4.5 12.75 6 6 9-13.5' : 'M6 18 18 6M6 6l12 12' }}"/>
                    </svg>
                    <div>
                        <p class="font-mono text-[length:var(--text-tiny)] uppercase tracking-[var(--tracking-label-xl)] {{ $valid ? 'text-emerald' : 'text-danger' }}">
                            {{ $valid ? __('Valid certificate') : __('Certificate not valid') }}
                        </p>
                        <p class="mt-1 font-mono text-[length:var(--text-h3)] text-ink-head">{{ $certificate->number }}</p>
                    </div>
                </div>
                <p class="text-right font-mono text-[length:var(--text-tiny)] text-ink-faint">
                    {{ __('Checked') }}<br>{{ now()->format('j M Y, H:i') }} MYT
                </p>
            </div>

            {{-- The record --}}
            <div class="grid gap-px bg-line sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['label' => __('Issuing body'), 'value' => $certificate->issuing_body.($certificate->issuing_body_name ? ' — '.$certificate->issuing_body_name : '')],
                    ['label' => __('Certificate holder'), 'value' => $certificate->holder_name.($certificate->store?->state ? ' · '.$certificate->store->state : '')],
                    ['label' => __('Scheme & scope'), 'value' => trim(($certificate->scheme ? $certificate->scheme.' — ' : '').($certificate->scope_note ?? ''), ' —')],
                    ['label' => __('Validity'), 'value' => $certificate->valid_from->format('j M Y').' → '.$certificate->valid_to->format('j M Y')
                        .($valid ? ' · '.trans_choice('{1} :count day remaining|[2,*] :count days remaining', $daysLeft, ['count' => $daysLeft]) : '')],
                    ['label' => __('Facility'), 'value' => $certificate->facility ?: '—'],
                    ['label' => __('Covered SKUs'), 'value' => number_format($certificate->products_count)],
                ] as $field)
                    <div class="bg-surface px-6 py-5">
                        <p class="font-mono text-[length:var(--text-nano)] uppercase tracking-[var(--tracking-label-xl)] text-ink-faint">{{ $field['label'] }}</p>
                        <p class="mt-2 text-[length:var(--text-md)] leading-relaxed text-ink-head">{{ $field['value'] }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Assurances actually recorded against this certificate --}}
            @if ($certificate->dedicated_facility || $certificate->export_paperwork)
                <div class="flex flex-wrap gap-2 border-t border-line bg-surface px-6 py-4">
                    @if ($certificate->dedicated_facility)
                        <span class="rounded-[var(--radius-pill)] border border-emerald-tint-edge bg-emerald-tint px-3 py-1 font-mono text-[length:var(--text-nano)] uppercase tracking-[var(--tracking-label)] text-emerald">{{ __('Dedicated halal facility') }}</span>
                    @endif
                    @if ($certificate->export_paperwork)
                        <span class="rounded-[var(--radius-pill)] border border-emerald-tint-edge bg-emerald-tint px-3 py-1 font-mono text-[length:var(--text-nano)] uppercase tracking-[var(--tracking-label)] text-emerald">{{ __('Export paperwork included') }}</span>
                    @endif
                </div>
            @endif

            {{-- Audit trail --}}
            @if ($certificate->events->isNotEmpty())
                <div class="border-t border-line bg-surface px-6 py-6">
                    <p class="font-mono text-[length:var(--text-tiny)] uppercase tracking-[var(--tracking-label-xl)] text-ink-faint">{{ __('Audit trail') }}</p>
                    <ol class="mt-4">
                        @foreach ($certificate->events as $event)
                            <li class="flex flex-wrap gap-x-8 gap-y-1 border-b border-line py-3 last:border-b-0">
                                <span class="w-28 shrink-0 font-mono text-[length:var(--text-tiny)] text-ink-faint">{{ $event->occurred_on->format('j M Y') }}</span>
                                <span class="min-w-0 flex-1 text-[length:var(--text-base)] text-ink">{{ $event->summary }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif

            {{-- What it covers --}}
            <div class="flex flex-wrap items-center justify-between gap-4 border-t border-line bg-cream px-6 py-5">
                <p class="max-w-[52ch] text-[length:var(--text-base)] text-ink-soft">
                    {{ __('This certificate is bound to the SKUs its scope names, not to the whole shop.') }}
                </p>
                <a href="{{ route('store.show', $certificate->store->slug) }}" wire:navigate
                   class="shrink-0 rounded-[var(--radius-pill)] border border-line-strong bg-surface px-5 py-2.5 text-[length:var(--text-base)] text-ink transition-colors duration-(--dur-micro) hover:border-ink-head hover:text-ink-head">
                    {{ trans_choice('{1} See the covered SKU|[2,*] See the :count covered SKUs', $certificate->products_count, ['count' => number_format($certificate->products_count)]) }}
                </a>
            </div>
        </div>
    @endif

    {{-- Recognised bodies, listed from the model rather than typed out here. --}}
    <div class="mt-12 border-t border-line pt-8">
        <p class="font-mono text-[length:var(--text-tiny)] uppercase tracking-[var(--tracking-label-xl)] text-ink-faint">{{ __('Recognised bodies') }}</p>
        <dl class="mt-4 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($bodies as $code => $meta)
                <div>
                    <dt class="font-mono text-[length:var(--text-base)] text-ink-head">{{ $code }}</dt>
                    <dd class="mt-1 text-[length:var(--text-sm)] leading-relaxed text-ink-soft">{{ $meta['name'] }}</dd>
                    <dd class="mt-1 font-mono text-[length:var(--text-nano)] uppercase tracking-[var(--tracking-label)] text-ink-faint">{{ $meta['prefix'] }}-…</dd>
                </div>
            @endforeach
        </dl>
    </div>
</div>
