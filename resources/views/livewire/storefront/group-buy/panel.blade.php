<div>
    @if ($deals->isNotEmpty())
        <section class="mt-6 overflow-hidden rounded-[var(--radius-card)] border border-brass/30 bg-surface shadow-soft" aria-label="{{ __('Group buy') }}">
            <div class="surface-girih flex items-center gap-2 border-b border-brass/25 bg-ink px-4 py-2.5 text-paper">
                <x-ui.star-mark :size="16" class="text-brass" />
                <h2 class="text-sm font-semibold">{{ __('Group buy — team up to unlock a lower price') }}</h2>
            </div>

            <div class="divide-y divide-line">
                @foreach ($deals as $deal)
                    @php($teams = $openTeams->get($deal->id) ?? collect())
                    <div class="p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="min-w-0">
                                @if ($deal->variant?->options_label)
                                    <p class="text-[13px] font-medium text-ink">{{ $deal->variant->options_label }}</p>
                                @endif
                                <p class="flex items-baseline gap-2">
                                    <span class="text-xl font-bold text-ink tnum">@price($deal->group_price_sen)</span>
                                    <span class="text-[13px] text-ink-soft">{{ __('with :n people', ['n' => $deal->target_size]) }}</span>
                                </p>
                            </div>
                            <button type="button" wire:click="start({{ $deal->id }})" wire:loading.attr="disabled" wire:target="start"
                                    class="inline-flex min-h-11 items-center justify-center rounded-[var(--radius-control)] bg-emerald px-4 text-sm font-semibold text-white transition-colors hover:bg-emerald-deep disabled:opacity-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-1">
                                {{ __('Start a group') }}
                            </button>
                        </div>

                        @if ($teams->isNotEmpty())
                            <p class="mt-3 text-[11px] font-semibold uppercase tracking-[0.06em] text-ink-faint">{{ __('Join an open group') }}</p>
                            <ul class="mt-2 space-y-1.5">
                                @foreach ($teams as $team)
                                    <li class="flex items-center justify-between gap-3 rounded-[var(--radius-control)] border border-line bg-paper px-3 py-2">
                                        <span class="min-w-0 text-[13px] text-ink-soft">
                                            {{ __(":name's group", ['name' => $team->initiator?->name ?? __('A shopper')]) }} ·
                                            {{ __(':joined of :target joined', ['joined' => $team->members_count, 'target' => $deal->target_size]) }}
                                        </span>
                                        <a href="{{ route('group-buy.team', $team->code) }}" wire:navigate
                                           class="shrink-0 text-[13px] font-semibold text-emerald hover:text-emerald-deep">{{ __('Join') }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
