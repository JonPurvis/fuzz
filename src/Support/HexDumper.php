<?php

declare(strict_types=1);

namespace Fuzz\Support;

final class HexDumper
{
    public function dump(string $payload, int $bytesPerLine = 16): string
    {
        $length = strlen($payload);
        $lines = [];
        $lines[] = "Payload ({$length} bytes):";

        if ($length === 0) {
            $lines[] = '  (empty)';

            return implode(PHP_EOL, $lines);
        }

        for ($offset = 0; $offset < $length; $offset += $bytesPerLine) {
            $chunk = substr($payload, $offset, $bytesPerLine);
            $hexParts = [];
            $ascii = '';

            for ($i = 0, $chunkLen = strlen($chunk); $i < $chunkLen; $i++) {
                $byte = ord($chunk[$i]);
                $hexParts[] = sprintf('%02X', $byte);
                $ascii .= ($byte >= 32 && $byte <= 126) ? $chunk[$i] : '.';
            }

            while (count($hexParts) < $bytesPerLine) {
                $hexParts[] = '  ';
            }

            $lines[] = sprintf('  %-47s | %s', implode(' ', $hexParts), $ascii);
        }

        return implode(PHP_EOL, $lines);
    }
}
