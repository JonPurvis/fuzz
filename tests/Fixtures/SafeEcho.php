<?php

declare(strict_types=1);

namespace Tests\Fixtures;

final class SafeEcho
{
    public static function handle(string $input): void
    {
        // Intentionally swallow all input without throwing.
        unset($input);
    }
}
