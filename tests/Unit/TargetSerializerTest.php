<?php

declare(strict_types=1);

use Fuzz\Support\TargetSerializer;
use Tests\Fixtures\SafeEcho;

it('serializes and restores a simple closure', function (): void {
    $serializer = new TargetSerializer;
    $encoded = $serializer->serialize(static function (string $input): string {
        return strtoupper($input);
    });

    $restored = $serializer->unserialize($encoded);

    expect($restored('abc'))->toBe('ABC');
});

it('writes and reads a fromCallable target via a temp file', function (): void {
    $path = sys_get_temp_dir().'/fuzz-target-'.bin2hex(random_bytes(4)).'.ser';
    $serializer = new TargetSerializer;

    $serializer->write($path, Closure::fromCallable([SafeEcho::class, 'handle']));
    $restored = $serializer->read($path);

    expect($restored)->toBeInstanceOf(Closure::class);

    $restored('noop');
    expect(true)->toBeTrue();
});

it('strips bound this so Pest closures can round-trip', function (): void {
    $serializer = new TargetSerializer;
    $bound = function (string $input): string {
        return strrev($input);
    };

    $restored = $serializer->unserialize($serializer->serialize($bound));

    expect($restored('abc'))->toBe('cba');
});
