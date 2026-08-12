<?php

declare(strict_types=1);

namespace Tests\Fixtures;

final class JwtVerifier
{
    /**
     * Buggy JWT verifier: explodes on "." and returns header alg without checks.
     */
    public static function verify(string $token): string
    {
        if ($token === '') {
            throw new InvalidTokenException('Token must not be empty.');
        }

        $parts = explode('.', $token);
        $headerJson = base64_decode($parts[0], true);
        /** @var mixed $header */
        $header = json_decode($headerJson === false ? '' : $headerJson, true);
        /** @var mixed $alg */
        $alg = is_array($header) ? ($header['alg'] ?? null) : null;

        return $alg;
    }
}
