<?php

declare(strict_types=1);

namespace App\Support;

final class HeadKey
{
    /**
     * Buggy helper: assumes JSON is always an object containing "key".
     */
    public static function parse(string $json): string
    {
        $data = json_decode($json, true);

        return $data['key'];
    }
}
