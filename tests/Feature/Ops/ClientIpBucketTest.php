<?php

use App\Support\ClientIp;

// H-1b follow-on: a limiter keyed on a full IPv6 address is not a limiter. One
// residential allocation is a /64 — millions of usable addresses — so an
// attacker rotating within their own subnet gets a fresh bucket every request.
test('IPv6 collapses to its /64 and IPv4 is left alone', function () {
    expect(ClientIp::bucket('203.0.113.9'))->toBe('203.0.113.9');

    // Same /64, three addresses → one bucket.
    $bucket = ClientIp::bucket('2001:db8:1:2::1');

    expect(ClientIp::bucket('2001:db8:1:2::dead:beef'))->toBe($bucket)
        ->and(ClientIp::bucket('2001:db8:1:2:ffff:ffff:ffff:ffff'))->toBe($bucket)
        ->and($bucket)->toEndWith('/64');

    // A different /64 is a different bucket.
    expect(ClientIp::bucket('2001:db8:1:3::1'))->not->toBe($bucket);

    // Garbage in never throws — a limiter must not be the thing that 500s.
    expect(ClientIp::bucket('not-an-ip'))->toBe('not-an-ip')
        ->and(ClientIp::bucket(''))->toBe('unknown');
});
