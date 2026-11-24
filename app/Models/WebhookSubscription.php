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
     *
     * Shared by SendWebhookJob (re-checked at SEND time, not just on write).
     *
     * Residual limitation (documented, not closed): the name is resolved here
     * and again by the HTTP client, so a DNS-rebinding answer between the two
     * is not caught. Closing it means pinning the checked IP for the request
     * (curl CURLOPT_RESOLVE), an HTTP-client change out of scope for this fix.
     */
    public static function isUrlSafe(string $url): bool
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? null;
        $host = trim((string) ($parts['host'] ?? ''), '[]');

        if ($host === '') {
            return false;
        }

        if ($scheme !== 'https' && ! ($scheme === 'http' && ! app()->environment('production'))) {
            return false;
        }

        if (strcasecmp($host, 'localhost') === 0) {
            return false;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : app(HostResolver::class)->ips($host);

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            // NO_PRIV + NO_RES: RFC1918, fc00::/7, loopback, link-local
            // (169.254.169.254 metadata), ::ffff:-mapped. GLOBAL_RANGE adds
            // 100.64/10 carrier NAT and the documentation ranges.
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE)) {
                return false;
            }
        }

        return true;
    }
}
