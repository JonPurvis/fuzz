<?php

declare(strict_types=1);

namespace Tests\Fixtures;

final class DomainExceptionThrower
{
    /**
     * Always throws a domain Exception for the given seed payload.
     */
    public static function handle(string $input): void
    {
        if ($input === 'bad') {
            throw new InvalidPayloadException('Rejected payload.');
        }
    }
}
