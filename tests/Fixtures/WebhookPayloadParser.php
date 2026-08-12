<?php

declare(strict_types=1);

namespace Tests\Fixtures;

final class WebhookPayloadParser
{
    /**
     * Buggy webhook parser: assumes decoded JSON always has a string "event".
     */
    public static function parse(string $json): string
    {
        /** @var mixed $data */
        $data = json_decode($json, true);
        /** @var mixed $event */
        $event = is_array($data) ? ($data['event'] ?? null) : null;

        return $event;
    }
}
