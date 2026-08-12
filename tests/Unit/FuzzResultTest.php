<?php

declare(strict_types=1);

use Fuzz\ValueObjects\FuzzResult;

it('reports ok and crashed states', function (): void {
    $ok = FuzzResult::success(10, 3, 2);
    $crash = new FuzzResult(
        crashed: true,
        runs: 5,
        features: 1,
        librarySize: 1,
        payload: '{}',
        exception: 'TypeError',
        crashPath: '/tmp/crash.txt',
        message: 'CRASH',
    );

    expect($ok->ok())->toBeTrue()
        ->and($ok->crashed())->toBeFalse()
        ->and($crash->ok())->toBeFalse()
        ->and($crash->crashed())->toBeTrue();
});

it('round trips payload through base64 in toArray/fromArray', function (): void {
    $original = new FuzzResult(
        crashed: true,
        runs: 12,
        features: 4,
        librarySize: 2,
        payload: "AB\x00C",
        exception: 'Error: boom',
        crashPath: '/tmp/crash-ab.txt',
        message: 'CRASH',
    );

    $restored = FuzzResult::fromArray($original->toArray());

    expect($restored->crashed)->toBeTrue()
        ->and($restored->runs)->toBe(12)
        ->and($restored->features)->toBe(4)
        ->and($restored->librarySize)->toBe(2)
        ->and($restored->payload)->toBe("AB\x00C")
        ->and($restored->exception)->toBe('Error: boom')
        ->and($restored->crashPath)->toBe('/tmp/crash-ab.txt')
        ->and($restored->message)->toBe('CRASH');
});

it('success factory builds a non-crash message', function (): void {
    $result = FuzzResult::success(100, 8, 5);

    expect($result->ok())->toBeTrue()
        ->and($result->message)->toContain('100 runs')
        ->and($result->message)->toContain('features: 8')
        ->and($result->message)->toContain('library: 5');
});

it('fromArray tolerates missing optional fields', function (): void {
    $result = FuzzResult::fromArray([
        'type' => 'result',
        'crashed' => false,
    ]);

    expect($result->ok())->toBeTrue()
        ->and($result->runs)->toBe(0)
        ->and($result->payload)->toBeNull()
        ->and($result->exception)->toBeNull()
        ->and($result->crashPath)->toBeNull()
        ->and($result->message)->toBe('');
});
