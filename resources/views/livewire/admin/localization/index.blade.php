<div class="space-y-6">

    <h1 class="font-display text-2xl font-bold">{{ __('Localization') }}</h1>

    {{-- ── Languages ─────────────────────────────────────────────────── --}}
    <section class="space-y-3">
        <h2 class="font-display text-lg font-semibold">{{ __('Languages') }}</h2>
        <x-ui.card>
            <ul class="divide-y divide-line">
                <li class="flex items-center gap-3 px-4 py-2">
                    <div class="flex-1">
                        <p class="text-[13px] font-semibold text-ink">{{ __('English') }} <span class="font-mono text-[12px] text-ink-faint">en</span></p>
                        <p class="text-[12px] text-ink-soft">{{ __('Fallback locale — every translation falls back to English.') }}</p>
                    </div>
                    <x-ui.badge variant="verified">{{ __('Always on') }}</x-ui.badge>
                </li>
                <li class="flex items-center gap-3 px-4 py-2">
                    <div class="flex-1">
                        <p class="text-[13px] font-semibold text-ink">{{ __('Bahasa Melayu') }} <span class="font-mono text-[12px] text-ink-faint">ms</span></p>
                        <p class="text-[12px] text-ink-soft">{{ __('Buyers can switch to BM when enabled.') }}</p>
                    </div>
                    <button type="button" role="switch" aria-checked="{{ $msEnabled ? 'true' : 'false' }}"
                            wire:click="toggleMs" aria-label="{{ __('Toggle Bahasa Melayu') }}"
                            class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                        <span class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-150 {{ $msEnabled ? 'bg-emerald' : 'bg-line-strong' }}">
                            <span class="inline-block size-4 rounded-full bg-white transition-transform duration-150 {{ $msEnabled ? 'translate-x-6' : 'translate-x-1' }}"></span>
                        </span>
                    </button>
                </li>
                <li class="flex items-center gap-3 px-4 py-2 opacity-50">
                    <div class="flex-1">
                        <p class="text-[13px] font-semibold text-ink">{{ __('Chinese (Simplified)') }} <span class="font-mono text-[12px] text-ink-faint">zh</span></p>
                        <p class="text-[12px] text-ink-soft">{{ __('Coming later — not available yet.') }}</p>
                    </div>
                    <x-ui.badge variant="neutral">{{ __('Coming later') }}</x-ui.badge>
                </li>
            </ul>
        </x-ui.card>
    </section>

    {{-- ── Currencies ────────────────────────────────────────────────── --}}
    <section class="space-y-3">
        <h2 class="font-display text-lg font-semibold">{{ __('Currencies') }}</h2>
        <p class="text-[13px] text-ink-soft">{{ __('Display-only conversion — storage, checkout, and settlement stay MYR.') }}</p>
        <x-ui.card class="overflow-x-auto">
            <table class="w-full min-w-[560px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Order') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Code') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Name') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Symbol') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Active') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($currencies as $currency)
                        <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="currency-{{ $currency->id }}">
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-0.5">
                                    <button type="button" wire:click="moveCurrency({{ $currency->id }}, -1)" @disabled($loop->first)
                                            aria-label="{{ __('Move :code up', ['code' => $currency->code]) }}"
                                            class="inline-flex size-11 items-center justify-center rounded-lg text-ink-soft hover:text-ink disabled:opacity-30 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5"/></svg>
                                    </button>
                                    <button type="button" wire:click="moveCurrency({{ $currency->id }}, 1)" @disabled($loop->last)
                                            aria-label="{{ __('Move :code down', ['code' => $currency->code]) }}"
                                            class="inline-flex size-11 items-center justify-center rounded-lg text-ink-soft hover:text-ink disabled:opacity-30 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="px-3 py-2 font-mono font-medium">
                                {{ $currency->code }}
                                @if ($currency->is_base)
                                    <x-ui.badge variant="verified" class="ml-1">{{ __('Base') }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-3 py-2">{{ $currency->name }}</td>
                            <td class="px-3 py-2">{{ $currency->symbol }}</td>
                            <td class="px-3 py-2">
                                <button type="button" role="switch" aria-checked="{{ $currency->is_active ? 'true' : 'false' }}"
                                        wire:click="toggleCurrency({{ $currency->id }})" @disabled($currency->is_base)
                                        aria-label="{{ __('Toggle :code', ['code' => $currency->code]) }}"
                                        class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald disabled:cursor-not-allowed disabled:opacity-50">
                                    <span class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-150 {{ $currency->is_active ? 'bg-emerald' : 'bg-line-strong' }}">
                                        <span class="inline-block size-4 rounded-full bg-white transition-transform duration-150 {{ $currency->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                    </span>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.card>
    </section>

    {{-- ── Exchange rates ────────────────────────────────────────────── --}}
    <section class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-display text-lg font-semibold">{{ __('Exchange rates') }}</h2>
            <div class="flex items-center gap-2 text-[13px] text-ink-faint">
                <span class="relative inline-flex h-6 w-11 items-center rounded-full bg-line-strong opacity-50" aria-hidden="true">
                    <span class="inline-block size-4 translate-x-1 rounded-full bg-white"></span>
                </span>
                {{ __('Automatic API sync (rates:sync) ships with M8 ops — rates are manual for now.') }}
            </div>
        </div>
        <p class="text-[13px] text-ink-soft">{{ __('Rates are append-only — saving writes a new row and the history keeps the trail.') }}</p>

        <x-ui.card class="overflow-x-auto">
            <table class="w-full min-w-[820px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Currency') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Rate (per RM 1)') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Margin %') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Source') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Fetched') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Update') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('History') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rateRows as $row)
                        @php
                            $currency = $row['currency'];
                            $latest = $row['latest'];
                            $code = $currency->code;
                        @endphp
                        <tr class="border-b border-line hover:bg-paper {{ $historyFor === $code ? '' : 'last:border-b-0' }}" wire:key="rate-{{ $code }}">
                            <td class="px-3 py-2 font-mono font-medium">{{ $code }}</td>
                            <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $latest !== null ? rtrim(rtrim((string) $latest->rate, '0'), '.') : '—' }}</td>
                            <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $latest !== null ? rtrim(rtrim((string) $latest->margin_percent, '0'), '.') : '—' }}</td>
                            <td class="px-3 py-2 text-ink-soft">{{ $latest->source ?? '—' }}</td>
                            <td class="px-3 py-2 whitespace-nowrap text-[12px] text-ink-soft">{{ $latest?->fetched_at?->diffForHumans() ?? __('Never') }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-start gap-2">
                                    <div>
                                        <label for="rate-{{ $code }}" class="sr-only">{{ __(':code rate', ['code' => $code]) }}</label>
                                        <input id="rate-{{ $code }}" type="text" inputmode="decimal" wire:model="rateInput.{{ $code }}" placeholder="0.21"
                                               class="block min-h-11 w-28 rounded-lg border bg-surface px-3 py-2 text-right font-mono text-[13px] focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('rateInput.'.$code) ? 'border-danger' : 'border-line-strong' }}">
                                        @error('rateInput.'.$code)<p class="mt-1 max-w-40 text-[12px] text-danger">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="margin-{{ $code }}" class="sr-only">{{ __(':code margin %', ['code' => $code]) }}</label>
                                        <input id="margin-{{ $code }}" type="text" inputmode="decimal" wire:model="marginInput.{{ $code }}" placeholder="0"
                                               class="block min-h-11 w-20 rounded-lg border bg-surface px-3 py-2 text-right font-mono text-[13px] focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('marginInput.'.$code) ? 'border-danger' : 'border-line-strong' }}">
                                        @error('marginInput.'.$code)<p class="mt-1 max-w-32 text-[12px] text-danger">{{ $message }}</p>@enderror
                                    </div>
                                    <button type="button" wire:click="updateRate('{{ $code }}')" wire:loading.attr="disabled"
                                            class="inline-flex min-h-11 items-center rounded-lg border border-ink px-3 text-[13px] font-semibold text-ink hover:bg-paper focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                        {{ __('Save rate') }}
                                    </button>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <button type="button" wire:click="toggleHistory('{{ $code }}')"
                                        class="inline-flex min-h-11 items-center rounded-lg px-2 font-medium text-ink-soft hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                    {{ $historyFor === $code ? __('Hide') : __('History') }}
                                </button>
                            </td>
                        </tr>
                        @if ($historyFor === $code)
                            <tr class="border-b border-line bg-paper last:border-b-0" wire:key="history-{{ $code }}">
                                <td colspan="7" class="px-4 py-3">
                                    <p class="mb-2 text-[12px] font-semibold uppercase tracking-[0.04em] text-ink-faint">{{ __('Last 10 rates — :code', ['code' => $code]) }}</p>
                                    @if ($history->isEmpty())
                                        <p class="text-[13px] text-ink-soft">{{ __('No rates recorded yet.') }}</p>
                                    @else
                                        <table class="w-full max-w-xl text-[12px]">
                                            <thead>
                                                <tr class="text-left text-ink-faint">
                                                    <th scope="col" class="py-1 pr-4 font-medium">{{ __('Rate') }}</th>
                                                    <th scope="col" class="py-1 pr-4 font-medium">{{ __('Margin %') }}</th>
                                                    <th scope="col" class="py-1 pr-4 font-medium">{{ __('Source') }}</th>
                                                    <th scope="col" class="py-1 font-medium">{{ __('Fetched at') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($history as $entry)
                                                    <tr wire:key="history-row-{{ $entry->id }}">
                                                        <td class="py-1 pr-4 font-mono tabular-nums">{{ rtrim(rtrim((string) $entry->rate, '0'), '.') }}</td>
                                                        <td class="py-1 pr-4 font-mono tabular-nums">{{ rtrim(rtrim((string) $entry->margin_percent, '0'), '.') }}</td>
                                                        <td class="py-1 pr-4">{{ $entry->source }}</td>
                                                        <td class="py-1">{{ $entry->fetched_at->format('d M Y H:i') }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </x-ui.card>
    </section>
</div>
