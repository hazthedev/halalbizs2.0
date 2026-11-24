<?php

namespace App\Models;

use App\Support\HostResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookSubscription extends Model
{
    protected $fillable = ['store_id', 'url', 'secret', 'events', 'is_active'];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** Active subscriptions wanting $event, scoped to the platform or a store. */
    public static function listeningFor(string $event, ?int $storeId = null): Collection
    {
        return static::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('store_id')->when($storeId !== null, fn ($q) => $q->orWhere('store_id', $storeId)))
            ->get()
            ->filter(fn (self $subscription) => in_array($event, $subscription->events ?? [], true))
            ->values();
    }

    /**
     * M5 SSRF guard: reject a URL unless it is https (http is allowed outside
     * production, for local/testing) AND every address its hostname resolves
     * to is public. SEC-46: IPv4 and IPv6 (A + AAAA through HostResolver), a
     * bracketed IPv6 literal is checked as an IP, and a name that does not
     * resolve is unsafe: "nothing to reach" was the old excuse, but a resolver
     * hiccup at check time can be followed by a private answer at connect time.
     * HB-G243: multicast and NAT64 (64:ff9b::/96) are refused too.
     *
     * Shared by SendWebhookJob (re-checked at SEND time, not just on write).
     */
    public static function isUrlSafe(string $url): bool
    {
        return static::checkedIps($url) !== null;
    }

    /**
     * The addresses the URL's host was checked against, or null when unsafe.
     * SendWebhookJob pins the connection to exactly these (HB-G243), so a
     * DNS-rebinding answer between check and connect is never used.
     *
     * @return list<string>|null
     */
    public static function checkedIps(string $url): ?array
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? null;
        $host = trim((string) ($parts['host'] ?? ''), '[]');

        if ($host === '') {
            return null;
        }

        if ($scheme !== 'https' && ! ($scheme === 'http' && ! app()->environment('production'))) {
            return null;
        }

        if (strcasecmp($host, 'localhost') === 0) {
            return null;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : app(HostResolver::class)->ips($host);

        if ($ips === []) {
            return null;
        }

        foreach ($ips as $ip) {
            // NO_PRIV + NO_RES: RFC1918, fc00::/7, loopback, link-local
            // (169.254.169.254 metadata), ::ffff:-mapped. GLOBAL_RANGE adds
            // 100.64/10 carrier NAT and the documentation ranges.
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE)) {
                return null;
            }

            // The flags above let these through: multicast (224.0.0.0/4,
            // ff00::/8), NAT64 64:ff9b::/96 and its local-use 64:ff9b:1::/48
            // (RFC 8215), which reach any IPv4 address, private ones included,
            // through a NAT64 gateway, and the old site-local fec0::/10.
            $bin = inet_pton($ip);

            if ((strlen($bin) === 4 && (ord($bin[0]) & 0xF0) === 0xE0)
                || (strlen($bin) === 16 && ($bin[0] === "\xff"
                    || ($bin[0] === "\xfe" && (ord($bin[1]) & 0xC0) === 0xC0)
                    || str_starts_with($bin, substr(inet_pton('64:ff9b::'), 0, 12))
                    || str_starts_with($bin, substr(inet_pton('64:ff9b:1::'), 0, 6))))) {
                return null;
            }
        }

        return $ips;
    }
}
