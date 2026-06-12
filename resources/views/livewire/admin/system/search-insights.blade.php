<div class="space-y-4">

    <h1 class="font-display text-2xl font-bold">{{ __('Search insights') }}</h1>

    {{-- Searches/day count line — text only, no chart needed at this volume --}}
    <p class="text-[13px] text-ink-soft">
        {{ trans_choice('{0}No searches in the last 14 days.|{1}:count search in the last 14 days|[2,*]:count searches in the last 14 days', $total14d, ['count' => number_format($total14d)]) }}
        @if ($total14d > 0)
            <span class="font-mono tabular-nums">(~{{ number_format($perDay14d) }}/{{ __('day') }})</span>
        @endif
    </p>

    <div class="grid gap-4 lg:grid-cols-2 lg:items-start">

        {{-- ===== Trending (last 7 days, with results) ===== --}}
        <x-ui.card class="overflow-x-auto">
            <div class="border-b border-line px-4 py-3">
                <h2 class="text-sm font-semibold">{{ __('Trending — last 7 days') }}</h2>
                <p class="text-[12px] text-ink-soft">{{ __('What buyers are finding. Top 20 terms with results.') }}</p>
            </div>

            @if ($trending->isEmpty())
                <div class="px-4 py-10 text-center">
                    <p class="font-display text-lg font-semibold">{{ __('Nothing trending yet') }}</p>
                    <p class="mt-1 text-sm text-ink-soft">{{ __('Terms appear here as buyers search.') }}</p>
                </div>
            @else
                <table class="w-full text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-ink-soft">
                            <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Term') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-right font-medium">{{ __('Searches') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-right font-medium">{{ __('Last seen') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($trending as $row)
                            <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="trend-{{ md5($row->term) }}">
                                <td class="px-4 py-2.5 font-medium">{{ $row->term }}</td>
                                <td class="px-4 py-2.5 text-right font-mono tabular-nums">{{ number_format($row->searches) }}</td>
                                <td class="px-4 py-2.5 whitespace-nowrap text-right text-ink-soft">{{ \Illuminate\Support\Carbon::parse($row->last_seen)->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-ui.card>

        {{-- ===== Zero-result (last 30 days) ===== --}}
        <x-ui.card class="overflow-x-auto">
            <div class="border-b border-line px-4 py-3">
                <h2 class="text-sm font-semibold">{{ __('Zero-result — last 30 days') }}</h2>
                <p class="text-[12px] text-ink-soft">{{ __('Buyers searched these and found nothing — consider creating a category or sourcing a product that matches.') }}</p>
            </div>

            @if ($zeroResult->isEmpty())
                <div class="px-4 py-10 text-center">
                    <p class="font-display text-lg font-semibold">{{ __('No dead-end searches') }}</p>
                    <p class="mt-1 text-sm text-ink-soft">{{ __('Every recent search found at least one product. Nice.') }}</p>
                </div>
            @else
                <table class="w-full text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-ink-soft">
                            <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Term') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-right font-medium">{{ __('Searches') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-right font-medium">{{ __('Last seen') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($zeroResult as $row)
                            <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="zero-{{ md5($row->term) }}">
                                <td class="px-4 py-2.5 font-medium">{{ $row->term }}</td>
                                <td class="px-4 py-2.5 text-right font-mono tabular-nums">{{ number_format($row->searches) }}</td>
                                <td class="px-4 py-2.5 whitespace-nowrap text-right text-ink-soft">{{ \Illuminate\Support\Carbon::parse($row->last_seen)->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-ui.card>
    </div>
</div>
