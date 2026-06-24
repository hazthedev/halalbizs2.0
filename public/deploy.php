<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Deploy webhook
|--------------------------------------------------------------------------
| Triggers deploy.sh over HTTPS so a deploy can be kicked off without SSH.
|
| Auth: a secret token. Set DEPLOY_TOKEN in the project .env (a long random
| string). The endpoint fails closed if it's unset.
|
|   GET  https://<host>/deploy.php?token=YOURTOKEN
|   or   header  X-Deploy-Token: YOURTOKEN
|
| Returns deploy.sh's combined output as plain text. If the host disables
| shell_exec (common on shared hosting) it says so — run deploy.sh via the
| cPanel Terminal / cron instead.
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
    exit("Deploy webhook disabled — set DEPLOY_TOKEN in .env to enable it.\n");
}

$provided = $_GET['token'] ?? $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '';

if (! is_string($provided) || ! hash_equals($secret, $provided)) {
    http_response_code(403);
    exit("Forbidden.\n");
}

$disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
if (! function_exists('shell_exec') || in_array('shell_exec', $disabled, true)) {
    http_response_code(500);
    exit("shell_exec is disabled on this host — run `bash deploy.sh` via the cPanel Terminal or a cron job instead.\n");
}

@set_time_limit(900);
@ini_set('max_execution_time', '900');

echo "→ deploy.php: running deploy.sh\n\n";
echo (string) shell_exec('cd '.escapeshellarg($root).' && bash deploy.sh 2>&1');
echo "\n→ deploy.php: done\n";
