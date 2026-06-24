@props(['product'])

@php
    $enabled = config('metafields.enabled', true);
    $values = $enabled ? $product->metafields->keyBy('key') : collect();
    $definitions = collect(config('metafields.definitions', []));
@endphp

@if ($values->isNotEmpty())
    <section class="mt-6 rounded-[var(--radius-card)] border border-line bg-surface p-4 shadow-soft" aria-label="{{ __('Product details') }}">
        @foreach (config('metafields.groups', []) as $groupKey => $groupLabel)
            @php($groupKeys = $definitions->filter(fn ($d) => ($d['group'] ?? 'details') === $groupKey)->keys())
            @php($rows = $groupKeys->map(fn ($key) => $values->get($key))->filter())
            @continue($rows->isEmpty())

            <div @class(['mt-4' => ! $loop->first])>
                @if ($groupKey === 'halal')
                    {{-- Halal certification: brass = premium/trust ornament --}}
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-brass/40 bg-brass/10 px-3 py-1 text-[13px] font-semibold text-brass-deep">
                            <x-ui.star-mark :size="14" class="text-brass" />
                            {{ __($groupLabel) }}
                        </span>
                    </div>
                    <dl class="mt-2.5 grid gap-x-6 gap-y-1.5 text-[13px] sm:grid-cols-2">
                        @foreach ($rows as $row)
                            <div class="flex justify-between gap-3 sm:block">
                                <dt class="text-ink-soft">{{ $row->label() }}</dt>
                                <dd class="font-medium text-ink">{{ $row->value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @else
                    <h3 class="text-[11px] font-semibold uppercase tracking-[0.06em] text-ink-faint">{{ __($groupLabel) }}</h3>
                    <dl class="mt-2 space-y-1.5 text-[13px]">
                        @foreach ($rows as $row)
                            <div class="sm:flex sm:gap-3">
                                <dt class="text-ink-soft sm:w-44 sm:shrink-0">{{ $row->label() }}</dt>
                                <dd class="text-ink {{ $row->type() === 'textarea' ? 'whitespace-pre-line' : '' }}">{{ $row->value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>
        @endforeach
    </section>
@endif
