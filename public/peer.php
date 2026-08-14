<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| TEMPORARY — proxy peer probe (audit H-1b). DELETE once trustProxies()
| names a real CIDR. Tracked in main/reminders.md.
|--------------------------------------------------------------------------
| trustProxies(at: '*') in bootstrap/app.php means $request->ip() is whatever
| X-Forwarded-For says, so every IP-keyed limiter is decorative. Narrowing it
| needs one fact nobody has: what REMOTE_ADDR actually is on this host.
| REMOTE_ADDR is the TCP peer — no header can forge it — so whatever this
| prints IS the real gateway.
|
| AppServiceProvider::logProxyPeer() already records it, but only to
| storage/logs/laravel.log, which is unreadable without shell access. This is
| the read path.
|
| Auth: the existing DEPLOY_TOKEN, header only.
|
|   curl -H 'X-Deploy-Token: YOURTOKEN' https://<host>/peer.php
|
| Header only, deliberately: deploy.php also accepts ?token= and the audit
| filed that as M-5 (an RCE-equivalent credential landing in access logs and
| referrers). No reason to repeat it in a new file.
*/

$root = dirname(__DIR__);

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');

/** Read a single key out of the project .env (no framework boot needed). */
$envValue = static function (string $envPath, string $key): ?string {
    if (! is_readable($envPath)) {
        return null;
    }
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || ! str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        if (trim($k) === $key) {
            return trim(trim($v), "\"'");
        }
    }

    return null;
};

$secret = $envValue($root.'/.env', 'DEPLOY_TOKEN');

if ($secret === null || $secret === '') {
    http_response_code(503);
    exit("Peer probe disabled — DEPLOY_TOKEN is unset.\n");
}

$provided = $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '';

if (! is_string($provided) || ! hash_equals($secret, $provided)) {
    http_response_code(403);
    exit("Forbidden.\n");
}

/*
 * REMOTE_ADDR is the answer. The forwarded headers are printed alongside so a
 * caller can tell an echo of their own request apart from a real proxy hop:
 * send none of them and any value that comes back was added upstream.
 */
$keys = [
    'REMOTE_ADDR',
    'HTTP_X_FORWARDED_FOR',
    'HTTP_X_REAL_IP',
    'HTTP_CF_CONNECTING_IP',
    'HTTP_X_FORWARDED_PROTO',
    'HTTP_X_FORWARDED_HOST',
    'SERVER_ADDR',
];

foreach ($keys as $key) {
    printf("%-24s %s\n", $key, $_SERVER[$key] ?? '(unset)');
}
