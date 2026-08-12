<?php

declare(strict_types=1);

use Fuzz\Exceptions\FuzzCrashException;
use Tests\Fixtures\CrashOnEmpty;

use function Fuzz\fuzz;

it('accepts dictionary entries from a .dict file path', function (): void {
    [$library, $crashes] = fuzzScratchDirs('fuzz-dict-file');
    $dict = dirname(__DIR__).'/Fixtures/dictionaries/json.dict';

    expect(function () use ($library, $crashes, $dict): void {
        fuzz(Closure::fromCallable([CrashOnEmpty::class, 'headKey']))
            ->runs(200)
            ->maxLen(64)
            ->seed(['{"key":"a"}'])
            ->withDictionary([$dict])
            ->libraryDir($library)
            ->crashDir($crashes)
            ->saveCrashes()
            ->run();
    })->toThrow(FuzzCrashException::class);
});
