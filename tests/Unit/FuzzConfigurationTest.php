<?php

declare(strict_types=1);

use Fuzz\Library\LibraryManager;
use Fuzz\ValueObjects\FuzzConfiguration;

it('uses sensible defaults', function (): void {
    $config = new FuzzConfiguration;

    expect($config->runs)->toBe(FuzzConfiguration::DEFAULT_RUNS)
        ->and($config->maxLen)->toBe(FuzzConfiguration::DEFAULT_MAX_LEN)
        ->and($config->timeout)->toBe(FuzzConfiguration::DEFAULT_TIMEOUT)
        ->and($config->saveCrashes)->toBeTrue()
        ->and($config->allowedExceptions)->toBe([Exception::class]);
});

it('round trips through array', function (): void {
    $config = (new FuzzConfiguration)
        ->withRuns(50)
        ->withMaxLen(128)
        ->withDictionary(['{', '}'])
        ->withSeeds(['{}'])
        ->withDescription('demo');

    $restored = FuzzConfiguration::fromArray($config->toArray());

    expect($restored->runs)->toBe(50)
        ->and($restored->maxLen)->toBe(128)
        ->and($restored->dictionary)->toBe(['{', '}'])
        ->and($restored->seeds)->toBe(['{}'])
        ->and($restored->description)->toBe('demo');
});

it('resolves default library and crash dirs from description hash', function (): void {
    $config = (new FuzzConfiguration)->withDescription('webhook parser');
    $base = '/tmp/project';
    $sep = DIRECTORY_SEPARATOR;

    expect($config->resolvedLibraryDir($base))->toContain(".pest{$sep}fuzz-library{$sep}")
        ->and($config->resolvedCrashDir($base))->toContain(".pest{$sep}fuzz-crashes{$sep}");
});

it('materializes inline dictionary keywords', function (): void {
    $dir = sys_get_temp_dir().'/fuzz-dict-'.bin2hex(random_bytes(4));
    $manager = new LibraryManager;
    $paths = $manager->materializeDictionaries($dir, ['{', 'null']);

    expect($paths)->toHaveCount(1)
        ->and(file_get_contents($paths[0]))->toContain('"{"')
        ->and(file_get_contents($paths[0]))->toContain('"null"');
});

it('supports boundary maxLen and timeout values', function (): void {
    $config = (new FuzzConfiguration)
        ->withMaxLen(1)
        ->withTimeout(1)
        ->withRuns(1);

    expect($config->maxLen)->toBe(1)
        ->and($config->timeout)->toBe(1)
        ->and($config->runs)->toBe(1);
});

it('resolves absolute library and crash dirs unchanged', function (): void {
    $config = (new FuzzConfiguration)
        ->withLibraryDir('/abs/lib')
        ->withCrashDir('/abs/crash');

    expect($config->resolvedLibraryDir('/tmp/project'))->toBe('/abs/lib')
        ->and($config->resolvedCrashDir('/tmp/project'))->toBe('/abs/crash');
});
