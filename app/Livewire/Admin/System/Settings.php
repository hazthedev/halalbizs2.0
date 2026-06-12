<?php

namespace App\Livewire\Admin\System;

use App\Models\Currency;
use App\Services\Ipay88Service;
use App\Settings\CodSettings;
use App\Settings\GeneralSettings;
use App\Settings\Ipay88Settings;
use App\Settings\ModerationSettings;
use App\Settings\OrderSettings;
use App\Settings\SecuritySettings;
use App\Settings\TrackingSettings;
use App\Support\RinggitInput;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Settings screens grouped per settings class (docs/08 §I). Each card saves
 * independently. Commission lives under Finance (another owner) — not here.
 * Encrypted secrets are write-only: inputs stay blank and only overwrite
 * when filled.
 */
#[Layout('layouts.admin')]
class Settings extends Component
{
    // ── General ────────────────────────────────────────────────────────
    public string $siteName = '';

    /** @var array<int, string> */
    public array $displayCurrencies = [];

    // ── Order ──────────────────────────────────────────────────────────
    public string $returnWindowDays = '';

    public string $autoCompleteDays = '';

    public string $unpaidExpiryMinutes = '';

    public string $payoutMin = '';

    // ── COD ────────────────────────────────────────────────────────────
    public bool $codEnabled = true;

    public string $codMaxOrder = '';

    // ── Moderation ─────────────────────────────────────────────────────
    public bool $requireProductApproval = false;

    // ── Security (Turnstile) ───────────────────────────────────────────
    public string $turnstileSiteKey = '';

    public string $turnstileSecret = '';

    public bool $turnstileSecretSet = false;

    // ── Tracking pixels ────────────────────────────────────────────────
    public string $ga4Id = '';

    public string $metaPixelId = '';

    public string $tiktokPixelId = '';

    // ── iPay88 ─────────────────────────────────────────────────────────
    public string $merchantCode = '';

    public string $merchantKey = '';

    public bool $merchantKeySet = false;

    public bool $sandbox = true;

    public function mount(): void
    {
        $general = app(GeneralSettings::class);
        $this->siteName = $general->site_name;
        $this->displayCurrencies = $general->display_currencies;

        $order = app(OrderSettings::class);
        $this->returnWindowDays = (string) $order->return_window_days;
        $this->autoCompleteDays = (string) $order->auto_complete_days;
        $this->unpaidExpiryMinutes = (string) $order->unpaid_expiry_minutes;
        $this->payoutMin = RinggitInput::fromSen($order->payout_min_sen);

        $cod = app(CodSettings::class);
        $this->codEnabled = $cod->enabled;
        $this->codMaxOrder = RinggitInput::fromSen($cod->max_order_sen);

        $this->requireProductApproval = app(ModerationSettings::class)->require_product_approval;

        $security = app(SecuritySettings::class);
        $this->turnstileSiteKey = $security->turnstile_site_key;
        $this->turnstileSecretSet = $security->turnstile_secret !== '';

        $tracking = app(TrackingSettings::class);
        $this->ga4Id = $tracking->ga4_id;
        $this->metaPixelId = $tracking->meta_pixel_id;
        $this->tiktokPixelId = $tracking->tiktok_pixel_id;

        $ipay88 = app(Ipay88Settings::class);
        $this->merchantCode = $ipay88->merchant_code;
        $this->merchantKeySet = $ipay88->merchant_key !== '';
        $this->sandbox = $ipay88->sandbox;
    }

    public function saveGeneral(): void
    {
        $this->validate([
            'siteName' => ['required', 'string', 'max:120'],
            'displayCurrencies' => ['array'],
            'displayCurrencies.*' => ['string', 'exists:currencies,code'],
        ]);

        // MYR is the base and is always displayable.
        $codes = collect(['MYR', ...$this->displayCurrencies])->unique()->values()->all();

        $settings = app(GeneralSettings::class);
        $settings->site_name = trim($this->siteName);
        $settings->display_currencies = $codes;
        $settings->save();

        $this->displayCurrencies = $codes;
        $this->dispatch('toast', message: __('General settings saved'));
    }

    public function saveOrder(): void
    {
        $this->validate([
            'returnWindowDays' => ['required', 'integer', 'min:1', 'max:60'],
            'autoCompleteDays' => ['required', 'integer', 'min:1', 'max:60'],
            'unpaidExpiryMinutes' => ['required', 'integer', 'min:5', 'max:10080'],
        ]);

        $payoutMinSen = RinggitInput::toSen($this->payoutMin);

        if ($payoutMinSen === null || $payoutMinSen < 0) {
            throw ValidationException::withMessages([
                'payoutMin' => __('Enter a valid RM amount — e.g. 50.00.'),
            ]);
        }

        $settings = app(OrderSettings::class);
        $settings->return_window_days = (int) $this->returnWindowDays;
        $settings->auto_complete_days = (int) $this->autoCompleteDays;
        $settings->unpaid_expiry_minutes = (int) $this->unpaidExpiryMinutes;
        $settings->payout_min_sen = $payoutMinSen;
        $settings->save();

        $this->dispatch('toast', message: __('Order settings saved'));
    }

    public function saveCod(): void
    {
        $maxOrderSen = RinggitInput::toSen($this->codMaxOrder);

        if ($maxOrderSen === null || $maxOrderSen <= 0) {
            throw ValidationException::withMessages([
                'codMaxOrder' => __('Enter a valid RM amount — e.g. 500.00.'),
            ]);
        }

        $settings = app(CodSettings::class);
        $settings->enabled = $this->codEnabled;
        $settings->max_order_sen = $maxOrderSen;
        $settings->save();

        $this->dispatch('toast', message: __('COD settings saved'));
    }

    public function saveModeration(): void
    {
        $settings = app(ModerationSettings::class);
        $settings->require_product_approval = $this->requireProductApproval;
        $settings->save();

        $this->dispatch('toast', message: __('Moderation settings saved'));
    }

    public function saveSecurity(): void
    {
        $this->validate([
            'turnstileSiteKey' => ['nullable', 'string', 'max:255'],
            'turnstileSecret' => ['nullable', 'string', 'max:255'],
        ]);

        $settings = app(SecuritySettings::class);
        $settings->turnstile_site_key = trim($this->turnstileSiteKey);

        // Write-only secret: blank input keeps the stored value.
        if (trim($this->turnstileSecret) !== '') {
            $settings->turnstile_secret = trim($this->turnstileSecret);
        }

        $settings->save();

        $this->turnstileSecret = '';
        $this->turnstileSecretSet = $settings->turnstile_secret !== '';
        $this->dispatch('toast', message: __('Security settings saved'));
    }

    public function saveTracking(): void
    {
        $this->validate([
            'ga4Id' => ['nullable', 'string', 'max:64'],
            'metaPixelId' => ['nullable', 'string', 'max:64'],
            'tiktokPixelId' => ['nullable', 'string', 'max:64'],
        ]);

        $settings = app(TrackingSettings::class);
        $settings->ga4_id = trim($this->ga4Id);
        $settings->meta_pixel_id = trim($this->metaPixelId);
        $settings->tiktok_pixel_id = trim($this->tiktokPixelId);
        $settings->save();

        $this->dispatch('toast', message: __('Tracking settings saved'));
    }

    public function saveIpay88(): void
    {
        $this->validate([
            'merchantCode' => ['nullable', 'string', 'max:64'],
            'merchantKey' => ['nullable', 'string', 'max:255'],
        ]);

        $settings = app(Ipay88Settings::class);
        $settings->merchant_code = trim($this->merchantCode);

        // Write-only key: blank input keeps the stored value.
        if (trim($this->merchantKey) !== '') {
            $settings->merchant_key = trim($this->merchantKey);
        }

        $settings->sandbox = $this->sandbox;
        $settings->save();

        $this->merchantKey = '';
        $this->merchantKeySet = $settings->merchant_key !== '';
        $this->dispatch('toast', message: __('iPay88 settings saved'));
    }

    /**
     * Requery with a dummy ref — a STRUCTURED error string back from the
     * gateway proves connectivity + credentials reach the right endpoint.
     */
    public function testIpay88Connection(): void
    {
        try {
            $response = app(Ipay88Service::class)->requery('TEST-CONNECTION', 100);

            $this->dispatch('toast', message: __('iPay88 responded: :response', [
                'response' => $response === '' ? __('(empty body)') : str($response)->limit(120),
            ]));
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: __('iPay88 connection failed: :error', [
                'error' => str($e->getMessage())->limit(120),
            ]), type: 'error');
        }
    }

    public function render()
    {
        return view('livewire.admin.system.settings', [
            'activeCurrencies' => Currency::query()->active()->get(),
        ])->title(__('Settings'));
    }
}
