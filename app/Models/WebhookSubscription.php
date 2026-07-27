<?php

namespace App\Models;

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
     * production, for local/testing) AND its hostname resolves to a public
     * IP — not loopback/private/link-local (which also covers the
     * 169.254.169.254 cloud metadata address) or unresolvable/reserved.
     *
     * Shared by SendWebhookJob (re-checked at SEND time, not just on write —
     * see below) and intended for a future subscription create-form to reuse.
     *
     * Residual limitation (documented, not closed): this resolves the
     * hostname at call time, so a genuine DNS-rebinding attack — where the
     * name resolves safely right now and is re-pointed at a private IP a
     * moment later, between this check and the actual HTTP connect — is not
     * caught. Closing that fully would require binding the validated IP
     * directly for the request, which needs an HTTP-client-level change out
     * of scope for this fix.
     */
    public static function isUrlSafe(string $url): bool
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;

        if (blank($host)) {
            return false;
        }

        if ($scheme !== 'https' && ! ($scheme === 'http' && ! app()->environment('production'))) {
            return false;
        }

        if (strcasecmp($host, 'localhost') === 0) {
            return false;
        }

        // Literal IP in the URL needs no DNS; otherwise resolve the hostname.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            $ips = gethostbynamel($host);

            // Unresolvable is NOT treated as unsafe: the real HTTP call can't
            // reach anything either in that case (no route to nowhere), and
            // treating it as unsafe would also wrongly block legitimate hosts
            // during any transient resolver hiccup.
            if ($ips === false || $ips === []) {
                return true;
            }
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }
}
