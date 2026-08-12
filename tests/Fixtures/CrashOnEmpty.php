<?php

declare(strict_types=1);

namespace Tests\Fixtures;

final class CrashOnEmpty
{
    public static function headKey(string $json): string
    {
        $data = json_decode($json, true);

        return $data['key'];
    }
}
