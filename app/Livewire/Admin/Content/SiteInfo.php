<?php

namespace App\Livewire\Admin\Content;

use App\Settings\SiteInfoSettings;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Contact details and social links for the web footer and the app config.
 * Goes live on save (no draft); saving needs cms.publish.
 */
#[Layout('layouts.admin')]
class SiteInfo extends Component
{
    /** Form property => setting key. */
    private const FIELDS = [
        'contactEmail' => 'contact_email',
        'contactPhone' => 'contact_phone',
        'contactWhatsapp' => 'contact_whatsapp',
        'contactAddress' => 'contact_address',
        'socialFacebook' => 'social_facebook',
        'socialInstagram' => 'social_instagram',
        'socialTiktok' => 'social_tiktok',
        'socialYoutube' => 'social_youtube',
        'socialX' => 'social_x',
    ];

    /** Social property => hosts allowed (www., m. and other subdomains too). */
    private const HOSTS = [
        'socialFacebook' => ['facebook.com'],
        'socialInstagram' => ['instagram.com'],
        'socialTiktok' => ['tiktok.com'],
        'socialYoutube' => ['youtube.com'],
        'socialX' => ['x.com', 'twitter.com'],
    ];

    public string $contactEmail = '';

    public string $contactPhone = '';

    public string $contactWhatsapp = '';

    public string $contactAddress = '';

    public string $socialFacebook = '';

    public string $socialInstagram = '';

    public string $socialTiktok = '';

    public string $socialYoutube = '';

    public string $socialX = '';

    public function mount(): void
    {
        $settings = app(SiteInfoSettings::class);

        foreach (self::FIELDS as $property => $key) {
            $this->$property = $settings->$key;
        }
    }

    public function save(): void
    {
        $this->authorize('cms.publish');

        $phone = ['nullable', 'regex:/^[0-9 +-]+$/', 'max:20'];
        $rules = [
            'contactEmail' => ['nullable', 'email', 'max:255'],
            'contactPhone' => $phone,
            'contactWhatsapp' => $phone,
            'contactAddress' => ['nullable', 'string', 'max:200'],
        ];

        foreach (self::HOSTS as $property => $hosts) {
            $rules[$property] = ['nullable', 'string', 'max:255', function ($attribute, $value, $fail) use ($hosts) {
                $host = strtolower((string) parse_url($value, PHP_URL_HOST));
                $ok = str_starts_with($value, 'https://') && collect($hosts)->contains(
                    fn ($h) => $host === $h || str_ends_with($host, '.'.$h),
                );

                $ok || $fail(__('Use a full https:// link to this network.'));
            }];
        }

        $this->validate($rules);

        $settings = app(SiteInfoSettings::class);
        $old = $new = [];

        foreach (self::FIELDS as $property => $key) {
            $value = trim($this->$property);
            $this->$property = $value;

            if ($value !== $settings->$key) {
                $old[$key] = $settings->$key;
                $new[$key] = $value;
                $settings->$key = $value;
            }
        }

        if ($new !== []) {
            $settings->save();

            activity('site-info')
                ->event('updated')
                ->withChanges(['old' => $old, 'attributes' => $new])
                ->log('Site info updated');
        }

        $this->dispatch('toast', message: __('Saved. It is live now.'));
    }

    public function render()
    {
        return view('livewire.admin.content.site-info', [
            'canPublish' => auth()->user()->can('cms.publish'),
        ])->title(__('Footer & contact'));
    }
}
