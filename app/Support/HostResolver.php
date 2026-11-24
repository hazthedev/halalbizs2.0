<?php

namespace App\Support;

/**
 * Every address a hostname resolves to, IPv4 and IPv6 (SEC-46). A class of
 * its own so tests can swap it out: no real DNS in the suite.
 */
class HostResolver
{
    /** @return list<string> empty when the name does not resolve */
    public function ips(string $host): array
    {
        // gethostbynamel reads /etc/hosts like the HTTP client does;
        // dns_get_record adds the AAAA answers gethostbynamel never returns.
        // ponytail: a hosts-file IPv6 entry is not seen; localhost is refused by name.
        $ips = gethostbynamel($host) ?: [];

        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            $ips[] = $record['ip'] ?? $record['ipv6'] ?? null;
        }

        return array_values(array_unique(array_filter($ips)));
    }
}
