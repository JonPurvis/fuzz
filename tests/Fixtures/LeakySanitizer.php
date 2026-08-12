<?php

declare(strict_types=1);

namespace Tests\Fixtures;

final class LeakySanitizer
{
    public static function clean(string $html): string
    {
        // Deliberately broken: does not remove script tags.
        return $html;
    }

    public static function assertClean(string $html): void
    {
        $clean = self::clean($html);

        if (str_contains($clean, '<script')) {
            throw new \Error('Unfiltered script token leaked');
        }
    }
}
