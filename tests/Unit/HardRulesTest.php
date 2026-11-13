<?php

use PHPUnit\Framework\Assert;

function hardRuleTokens(string $file): array
{
    $tokens = [];
    $line = 1;
    foreach (token_get_all(file_get_contents($file)) as $token) {
        $value = is_array($token) ? $token[1] : $token;
        if (! is_array($token) || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $tokens[] = [$value, $line];
        }
        $line += substr_count($value, "\n");
    }

    return $tokens;
}

function hardRuleScan(array $directories, callable $check): array
{
    $found = [];
    foreach ($directories as $directory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $tokens = hardRuleTokens($file->getPathname());
            foreach ($tokens as $i => [$value, $line]) {
                if ($check($tokens, $i)) {
                    $found[] = $file->getPathname().':'.$line;
                }
            }
        }
    }
    sort($found);

    return $found;
}

function hardRuleFunction(array $tokens, int $i, array $names): bool
{
    return in_array(strtolower($tokens[$i][0]), $names, true)
        && ($tokens[$i + 1][0] ?? null) === '('
        && ! in_array(strtolower($tokens[$i - 1][0] ?? ''), ['->', '?->', '::', 'function', 'fn'], true);
}

it('keeps env calls out of app and routes', function () {
    $found = hardRuleScan(['app', 'routes'], fn ($tokens, $i) => hardRuleFunction($tokens, $i, ['env']));
    Assert::assertSame([], $found, implode("\n", $found));
});

it('keeps debug calls out of app', function () {
    $found = hardRuleScan(['app'], fn ($tokens, $i) => hardRuleFunction($tokens, $i, ['dd', 'dump', 'ray', 'var_dump']));
    Assert::assertSame([], $found, implode("\n", $found));
});

it('keeps rate limiter definitions out of routes', function () {
    $found = hardRuleScan(['routes'], fn ($tokens, $i) => strtolower($tokens[$i][0]) === 'ratelimiter'
        && ($tokens[$i + 1][0] ?? null) === '::'
        && strtolower($tokens[$i + 2][0] ?? '') === 'for'
        && ($tokens[$i + 3][0] ?? null) === '(');
    Assert::assertSame([], $found, implode("\n", $found));
});

it('keeps writes off the whip connection', function () {
    $found = hardRuleScan(['app'], function ($tokens, $i) {
        if (strtolower($tokens[$i][0]) !== 'connection' || ($tokens[$i + 1][0] ?? null) !== '('
            || ! in_array($tokens[$i + 2][0] ?? '', ["'whip'", '"whip"'], true)
            || ($tokens[$i + 3][0] ?? null) !== ')') {
            return false;
        }
        for ($j = $i + 4; isset($tokens[$j]) && ! in_array($tokens[$j][0], [';', '{', '}'], true); $j++) {
            if (in_array($tokens[$j][0], ['->', '?->'], true)
                && in_array(strtolower($tokens[$j + 1][0] ?? ''), ['insert', 'update', 'delete', 'statement', 'unprepared'], true)
                && ($tokens[$j + 2][0] ?? null) === '(') {
                return true;
            }
        }

        return false;
    });
    Assert::assertSame([], $found, implode("\n", $found));
});
