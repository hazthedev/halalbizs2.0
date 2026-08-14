<?php

use App\Listeners\AwardCoinsOnCompletion;
use App\Listeners\IssueEInvoiceOnOrderPaid;
use App\Listeners\RecordAffiliateCommissionOnCompletion;

// The deploy and resilience MEDIUMs: M-26, M-27, M-28, M-30, M-31, M-12, M-13.
//
// deploy.sh is shell and never runs under Pest, so those three are asserted
// against its SOURCE. That is weaker than executing it — it pins the ordering
// and the failure plumbing, not the behaviour — but the alternative was no
// check at all on a script that does `git reset --hard` then migrates.

function deployScript(): string
{
    return file_get_contents(base_path('deploy.sh'));
}

// ── M-26 · every schema and data step read the PREVIOUS deploy's config ──
test('the caches are cleared before migrate, not after the seeders', function () {
    $sh = deployScript();

    $clear = strpos($sh, 'artisan optimize:clear');
    $migrate = strpos($sh, 'artisan migrate --force');
    $firstSeed = strpos($sh, 'artisan db:seed');

    // LoadEnvironmentVariables returns early when the config is cached, so a
    // clear that happens AFTER these steps never helped them.
    expect($clear)->toBeLessThan($migrate)
        ->and($clear)->toBeLessThan($firstSeed);
});

// ── M-27 · nine swallowed steps under a ✓ ────────────────────────────────
test('a seeder failure cannot be reported as a successful deploy', function () {
    $sh = deployScript();

    // Every `|| echo … continuing` must now also record that it happened.
    preg_match_all('/\|\| \{? ?STEP_FAILED=1;/', $sh, $recorded);
    preg_match_all('/\|\| echo "  ! /', $sh, $unrecorded);

    expect($recorded[0])->not->toBeEmpty()
        ->and($unrecorded[0])->toBeEmpty()
        ->and($sh)->toContain('WITH SEED/INDEX ERRORS');
});

// ── M-28 · the two readers disagreed with phpdotenv ──────────────────────
test('both env readers take the last duplicate, like phpdotenv', function () {
    // Assert on the read_env LINE, not the whole file — the comment above it
    // names `head -1` as the thing that was wrong, and a naive grep for the
    // string matches that explanation and fails.
    preg_match('/^read_env\(\).*$/m', deployScript(), $line);

    expect($line[0])->toContain('tail -1')->not->toContain('head -1');

    // The PHP reader keeps scanning instead of returning on the first match.
    $php = file_get_contents(base_path('public/deploy.php'));
    expect($php)->toContain('$found = trim(trim($v)');
});

// ── M-30 · turning on subdomains logged everyone out ─────────────────────
test('the subdomain block warns about SESSION_DOMAIN', function () {
    $env = file_get_contents(base_path('.env.example'));
    $block = substr($env, strpos($env, 'STORE_SUBDOMAIN_BASE'));

    expect(substr($block, 0, 600))->toContain('SESSION_DOMAIN');
});

// ── M-31 · the preview is fully indexable ────────────────────────────────
test('noindex is off by default and on when the flag is set', function () {
    config(['app.noindex' => false]);
    $this->get('/')->assertHeaderMissing('X-Robots-Tag');

    config(['app.noindex' => true]);
    $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

// ── M-12 · the catch that disabled the only recovery there was ───────────
test('the queued listeners can actually retry', function () {
    foreach ([AwardCoinsOnCompletion::class, RecordAffiliateCommissionOnCompletion::class, IssueEInvoiceOnOrderPaid::class] as $listener) {
        $instance = app($listener);

        expect($instance->tries)->toBeGreaterThan(1, "{$listener} has no \$tries")
            ->and($instance->backoff())->not->toBeEmpty();

        // A catch-all around the body makes $tries dead config: the job returns
        // normally, the queue records success, and nothing is ever retried.
        expect(file_get_contents((new ReflectionClass($listener))->getFileName()))
            ->not->toContain('catch (Throwable', "{$listener} still swallows Throwable");
    }
});

// ── M-13 · a network blip was indistinguishable from a rejection ─────────
test('a transport failure is retried, while a rejection stays terminal', function () {
    $source = file_get_contents(base_path('app/Services/EInvoice/MyInvoisProvider.php'));

    // ConnectionException rethrows so the queued listener retries; anything else
    // still returns failed(), which is correct — LHDN will not change its mind.
    expect($source)->toContain('catch (ConnectionException $e)')
        ->and($source)->toContain('throw $e;')
        ->and($source)->toContain('EInvoiceResult::failed($e->getMessage())');

    expect(strpos($source, 'catch (ConnectionException'))
        ->toBeLessThan(strpos($source, 'catch (Throwable $e) {'));
});
