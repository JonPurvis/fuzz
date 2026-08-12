<?php

declare(strict_types=1);

use Fuzz\FuzzCall;
use Fuzz\ValueObjects\FuzzConfiguration;
use Tests\Fixtures\InvalidPayloadException;
use Tests\Fixtures\SafeEcho;

use function Fuzz\fuzz;

it('fluent chain mutates configuration without running the worker', function (): void {
    $call = fuzz(Closure::fromCallable([SafeEcho::class, 'handle']))
        ->runs(42)
        ->maxLen(128)
        ->timeout(7)
        ->withDictionary(['{', '}'])
        ->seed(['hello'])
        ->libraryDir('/tmp/fuzz-lib')
        ->crashDir('/tmp/fuzz-crash')
        ->catchCrashes(false)
        ->allow([InvalidPayloadException::class]);

    expect($call)->toBeInstanceOf(FuzzCall::class);

    $config = $call->configuration();

    expect($config->runs)->toBe(42)
        ->and($config->maxLen)->toBe(128)
        ->and($config->timeout)->toBe(7)
        ->and($config->dictionary)->toBe(['{', '}'])
        ->and($config->seeds)->toBe(['hello'])
        ->and($config->libraryDir)->toBe('/tmp/fuzz-lib')
        ->and($config->crashDir)->toBe('/tmp/fuzz-crash')
        ->and($config->catchCrashes)->toBeFalse()
        ->and($config->allowedExceptions)->toBe([InvalidPayloadException::class]);
});

it('run description updates configuration before execution', function (): void {
    $call = fuzz(Closure::fromCallable([SafeEcho::class, 'handle']))
        ->runs(1)
        ->maxLen(8)
        ->seed(['x']);

    [$library, $crashes] = fuzzScratchDirs('fuzz-call-desc');

    $call->libraryDir($library)->crashDir($crashes)->run('named fuzz');

    expect($call->configuration()->description)->toBe('named fuzz')
        ->and($call->configuration()->runs)->toBe(1);
});

it('starts from default configuration when none is provided', function (): void {
    $config = fuzz(Closure::fromCallable([SafeEcho::class, 'handle']))->configuration();

    expect($config->runs)->toBe(FuzzConfiguration::DEFAULT_RUNS)
        ->and($config->maxLen)->toBe(FuzzConfiguration::DEFAULT_MAX_LEN)
        ->and($config->timeout)->toBe(FuzzConfiguration::DEFAULT_TIMEOUT)
        ->and($config->catchCrashes)->toBeTrue();
});
