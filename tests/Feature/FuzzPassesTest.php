<?php

declare(strict_types=1);

use Tests\Fixtures\SafeEcho;

use function Fuzz\fuzz;

it('passes when the target never crashes', function (): void {
    [$library, $crashes] = fuzzScratchDirs('fuzz-safe');

    $result = fuzz(Closure::fromCallable([SafeEcho::class, 'handle']))
        ->runs(30)
        ->maxLen(32)
        ->seed(['hello', 'world'])
        ->libraryDir($library)
        ->crashDir($crashes)
        ->run();

    expect($result->ok())->toBeTrue()
        ->and($result->runs)->toBeGreaterThan(0);
});
