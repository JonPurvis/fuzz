<?php

declare(strict_types=1);

use Fuzz\Exceptions\FuzzCrashException;
use Tests\Fixtures\DomainExceptionThrower;
use Tests\Fixtures\InvalidPayloadException;

use function Fuzz\fuzz;

it('allows configured domain exceptions without treating them as crashes', function (): void {
    [$library, $crashes] = fuzzScratchDirs('fuzz-allow');

    $result = fuzz(Closure::fromCallable([DomainExceptionThrower::class, 'handle']))
        ->runs(40)
        ->maxLen(16)
        ->seed(['bad', 'ok'])
        ->allow([InvalidPayloadException::class])
        ->libraryDir($library)
        ->crashDir($crashes)
        ->run();

    expect($result->ok())->toBeTrue();
});

it('treats disallowed domain exceptions as crashes', function (): void {
    [$library, $crashes] = fuzzScratchDirs('fuzz-disallow');

    expect(function () use ($library, $crashes): void {
        fuzz(Closure::fromCallable([DomainExceptionThrower::class, 'handle']))
            ->runs(40)
            ->maxLen(16)
            ->seed(['bad'])
            ->allow([])
            ->libraryDir($library)
            ->crashDir($crashes)
            ->saveCrashes()
            ->run();
    })->toThrow(FuzzCrashException::class);
});
