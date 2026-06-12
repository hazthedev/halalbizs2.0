<?php

namespace App\Livewire\Admin\Localization;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Localization (docs/08 §H): languages (en locked on), currency toggles,
 * and append-only exchange rates. A manual rate update writes a NEW row —
 * never edits — and busts the fx:{code} cache CurrencyConverter reads.
 */
#[Layout('layouts.admin')]
class Index extends Component
{
    /** @var array<string, string> rate input per currency code */
    public array $rateInput = [];

    /** @var array<string, string> margin % input per currency code */
    public array $marginInput = [];

    /** Currency code whose history drawer is open. */
    public ?string $historyFor = null;

    public function mount(): void
    {
        foreach (Currency::where('is_base', false)->get() as $currency) {
            $latest = ExchangeRate::latestFor($currency->code);
            $this->rateInput[$currency->code] = $latest !== null ? rtrim(rtrim((string) $latest->rate, '0'), '.') : '';
            $this->marginInput[$currency->code] = $latest !== null ? rtrim(rtrim((string) $latest->margin_percent, '0'), '.') : '0';
        }
    }

    // ── Languages ──────────────────────────────────────────────────────

    public function toggleMs(): void
    {
        $settings = app(GeneralSettings::class);
        $enabling = ! in_array('ms', $settings->enabled_locales, true);

        // en is always present; ms toggles. (zh is "coming later" — no toggle.)
        $settings->enabled_locales = $enabling ? ['en', 'ms'] : ['en'];
        $settings->save();

        $this->dispatch('toast', message: $enabling
            ? __('Bahasa Melayu enabled')
            : __('Bahasa Melayu disabled'));
    }

    // ── Currencies ─────────────────────────────────────────────────────

    public function toggleCurrency(int $currencyId): void
    {
        $currency = Currency::findOrFail($currencyId);

        if ($currency->is_base) {
            $this->dispatch('toast', message: __('MYR is the base currency and stays on.'), type: 'error');

            return;
        }

        $currency->update(['is_active' => ! $currency->is_active]);

        $this->dispatch('toast', message: $currency->is_active
            ? __(':code enabled', ['code' => $currency->code])
            : __(':code disabled', ['code' => $currency->code]));
    }

    /** Swap with the neighbour, then re-index positions 0..n. */
    public function moveCurrency(int $currencyId, int $direction): void
    {
        $currencies = Currency::orderBy('position')->orderBy('id')->get()->values();
        $index = $currencies->search(fn (Currency $currency) => $currency->id === $currencyId);
        $target = $index === false ? false : $index + ($direction < 0 ? -1 : 1);

        if ($index === false || $target < 0 || ! isset($currencies[$target])) {
            return;
        }

        $ordered = $currencies->all();
        [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

        foreach (array_values($ordered) as $position => $currency) {
            if ($currency->position !== $position) {
                $currency->update(['position' => $position]);
            }
        }
    }

    // ── Exchange rates (append-only) ───────────────────────────────────

    public function updateRate(string $code): void
    {
        $currency = Currency::where('code', $code)->where('is_base', false)->firstOrFail();

        $rate = trim($this->rateInput[$code] ?? '');
        $margin = trim($this->marginInput[$code] ?? '');
        $margin = $margin === '' ? '0' : $margin;

        // Rate is a decimal STRING end to end — it's a ratio, not money, but
        // we still never touch floats. > 0 check: any non-zero digit present.
        if (! preg_match('/^\d{1,8}(\.\d{1,8})?$/', $rate) || ltrim(str_replace('.', '', $rate), '0') === '') {
            $this->addError("rateInput.{$code}", __('Enter a rate above 0 — e.g. 0.21.'));

            return;
        }

        [$marginUnits, $marginFrac] = array_pad(explode('.', $margin, 2), 2, '');

        if (! preg_match('/^\d{1,3}(\.\d{1,2})?$/', $margin)
            || (int) $marginUnits > 100
            || ((int) $marginUnits === 100 && (int) $marginFrac > 0)) {
            $this->addError("marginInput.{$code}", __('Margin is a percentage from 0 to 100.'));

            return;
        }

        $this->resetErrorBag(["rateInput.{$code}", "marginInput.{$code}"]);

        // Append-only: a new row, never an update. latestFor() picks it up.
        ExchangeRate::create([
            'currency_code' => $currency->code,
            'rate' => $rate,
            'margin_percent' => $margin,
            'source' => 'manual',
            'fetched_at' => now(),
        ]);

        // CurrencyConverter caches the effective rate — clear it now.
        Cache::forget("fx:{$currency->code}");

        $this->dispatch('toast', message: __(':code rate updated', ['code' => $currency->code]));
    }

    public function toggleHistory(string $code): void
    {
        $this->historyFor = $this->historyFor === $code ? null : $code;
    }

    public function render()
    {
        $settings = app(GeneralSettings::class);
        $currencies = Currency::orderBy('position')->orderBy('id')->get();
        $nonBase = $currencies->where('is_base', false)->values();

        return view('livewire.admin.localization.index', [
            'msEnabled' => in_array('ms', $settings->enabled_locales, true),
            'currencies' => $currencies,
            'rateRows' => $nonBase->map(fn (Currency $currency) => [
                'currency' => $currency,
                'latest' => ExchangeRate::latestFor($currency->code),
            ]),
            'history' => $this->historyFor !== null
                ? ExchangeRate::where('currency_code', $this->historyFor)
                    ->orderByDesc('fetched_at')
                    ->orderByDesc('id')
                    ->take(10)
                    ->get()
                : collect(),
        ])->title(__('Localization'));
    }
}
