<?php

declare(strict_types=1);

namespace Tests\Fixtures;

final class Slugger
{
    public static function make(string $input): string
    {
        $slug = strtolower($input);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }
}
