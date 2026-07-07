<?php

namespace App\Support;

/**
 * URL-safe encoding for aggregate/exception-group keys used as a path segment
 * on the drill-down detail endpoints.
 *
 * Aggregate keys are arbitrary strings — route paths (`/api/orders`), FQCNs
 * (`App\Jobs\ProcessPayment`), raw command strings, parameterized SQL, host
 * URLs — so they can contain `/`, `\`, spaces, quotes, and `?`, none of which
 * survive a raw path segment. We base64url-encode the raw key (RFC 4648 §5:
 * base64 with `+`→`-`, `/`→`_`, padding stripped), giving a single opaque,
 * path-safe segment the SPA round-trips without escaping.
 */
class AggregateKey
{
    public static function encode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    public static function decode(string $encoded): string
    {
        // base64_decode tolerates the missing '=' padding; strtr restores the
        // standard base64 alphabet first.
        return (string) base64_decode(strtr($encoded, '-_', '+/'));
    }
}
