<?php

declare(strict_types=1);

use Fuzz\Support\HexDumper;

it('formats empty payloads', function (): void {
    $dump = (new HexDumper)->dump('');

    expect($dump)->toContain('Payload (0 bytes)')
        ->and($dump)->toContain('(empty)');
});

it('formats binary payloads with hex and ascii', function (): void {
    $dump = (new HexDumper)->dump("AB\x00C");

    expect($dump)->toContain('Payload (4 bytes)')
        ->and($dump)->toContain('41 42 00 43')
        ->and($dump)->toContain('AB.C');
});

it('wraps payloads longer than sixteen bytes onto multiple lines', function (): void {
    $payload = str_repeat('A', 20);
    $dump = (new HexDumper)->dump($payload);

    expect($dump)->toContain('Payload (20 bytes)')
        ->and(substr_count($dump, PHP_EOL))->toBeGreaterThanOrEqual(2)
        ->and($dump)->toContain(str_repeat('41 ', 15).'41')
        ->and($dump)->toContain('41 41 41 41');
});
