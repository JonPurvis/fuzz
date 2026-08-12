<?php

declare(strict_types=1);

use Fuzz\Exceptions\FuzzCrashException;
use Tests\Fixtures\CrashOnEmpty;
use Tests\Fixtures\SafeEcho;

use function Fuzz\fuzz;

it('runs with an empty library start and tiny maxLen', function (): void {
    [$library, $crashes] = fuzzScratchDirs('fuzz-boundary-empty');

    $result = fuzz(Closure::fromCallable([SafeEcho::class, 'handle']))
        ->runs(20)
        ->maxLen(1)
        ->libraryDir($library)
        ->crashDir($crashes)
        ->run();

    expect($result->ok())->toBeTrue();
});

it('accepts a null-byte seed without crashing a safe target', function (): void {
    [$library, $crashes] = fuzzScratchDirs('fuzz-boundary-nul');

    $result = fuzz(Closure::fromCallable([SafeEcho::class, 'handle']))
        ->runs(20)
        ->maxLen(8)
        ->seed(["\0", "a\0b"])
        ->libraryDir($library)
        ->crashDir($crashes)
        ->run();

    expect($result->ok())->toBeTrue();
});

it('respects a small maxLen while still finding crashes', function (): void {
    [$library, $crashes] = fuzzScratchDirs('fuzz-boundary-maxlen');

    // "{}" is 2 bytes; with seeds that already crash we only need tiny runs.
    expect(function () use ($library, $crashes): void {
        fuzz(Closure::fromCallable([CrashOnEmpty::class, 'headKey']))
            ->runs(30)
            ->maxLen(8)
            ->seed(['{}'])
            ->libraryDir($library)
            ->crashDir($crashes)
            ->catchCrashes()
            ->run();
    })->toThrow(FuzzCrashException::class);
});
