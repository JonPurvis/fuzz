<?php

declare(strict_types=1);

use Fuzz\Exceptions\FuzzCrashException;
use Tests\Fixtures\CrashOnEmpty;

use function Fuzz\fuzz;

it('includes a hex dump and crash path when catching crashes', function (): void {
    [$library, $crashes] = fuzzScratchDirs('fuzz-report');

    try {
        // Seed the known crashing payload so CORPUS CRASH is deterministic and persisted.
        fuzz(Closure::fromCallable([CrashOnEmpty::class, 'headKey']))
            ->runs(10)
            ->maxLen(64)
            ->seed(['{}'])
            ->libraryDir($library)
            ->crashDir($crashes)
            ->saveCrashes()
            ->run();

        $this->fail('Expected FuzzCrashException');
    } catch (FuzzCrashException $exception) {
        expect($exception->getMessage())->toContain('Crash saved:')
            ->and($exception->getMessage())->toContain('Payload (')
            ->and($exception->getMessage())->toMatch('/[0-9A-F]{2}/');

        $files = glob($crashes.DIRECTORY_SEPARATOR.'crash-*.txt') ?: [];
        expect($files)->not->toBeEmpty();
    }
});

it('still crashes but does not persist a crash file when saveCrashes is false', function (): void {
    [$library, $crashes] = fuzzScratchDirs('fuzz-nocatch');

    try {
        fuzz(Closure::fromCallable([CrashOnEmpty::class, 'headKey']))
            ->runs(10)
            ->maxLen(64)
            ->seed(['{}'])
            ->libraryDir($library)
            ->crashDir($crashes)
            ->saveCrashes(false)
            ->run();

        $this->fail('Expected FuzzCrashException');
    } catch (FuzzCrashException $exception) {
        expect($exception->getMessage())->not->toContain('Crash saved:');

        $files = glob($crashes.DIRECTORY_SEPARATOR.'crash-*.txt') ?: [];
        expect($files)->toBeEmpty();
    }
});

it('persists crash-*.txt for mutation crashes found from safe seeds', function (): void {
    [$library, $crashes] = fuzzScratchDirs('fuzz-mutation-crash');

    try {
        // Safe seeds only — the crash must come from mutation, not a CORPUS CRASH seed.
        fuzz(Closure::fromCallable([CrashOnEmpty::class, 'headKey']))
            ->runs(400)
            ->maxLen(64)
            ->seed(['{"key":"ok"}'])
            ->withDictionary(['{', '}', 'null', 'key', ':', ',', '"'])
            ->libraryDir($library)
            ->crashDir($crashes)
            ->saveCrashes()
            ->run();

        $this->fail('Expected FuzzCrashException');
    } catch (FuzzCrashException $exception) {
        expect($exception->getMessage())->toContain('Crash saved:');

        $files = glob($crashes.DIRECTORY_SEPARATOR.'crash-*.txt') ?: [];
        expect($files)->not->toBeEmpty();
    }
});
