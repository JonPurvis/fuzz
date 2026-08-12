<?php

declare(strict_types=1);

use Fuzz\Exceptions\FuzzCrashException;
use Tests\Fixtures\PricingEngine;

use function Fuzz\fuzz;

it('datasets regress known crashing pricing inputs', function (string $input): void {
    expect(fn () => PricingEngine::quote($input))->toThrow(DivisionByZeroError::class);
})->with([
    'denominator zero' => ['100,0'],
    'zero over zero' => ['0,0'],
]);

it('datasets stay green for known good pricing inputs', function (string $input, float $expected): void {
    expect(PricingEngine::quote($input))->toBe($expected);
})->with([
    'simple quote' => ['100,2', 50.0],
    'unity' => ['10,1', 10.0],
]);

it('fuzz finds hostile pricing inputs around seeds', function (): void {
    [$library, $crashes] = fuzzScratchDirs('fuzz-pricing');

    expect(function () use ($library, $crashes): void {
        fuzz(Closure::fromCallable([PricingEngine::class, 'quote']))
            ->runs(150)
            ->maxLen(32)
            ->seed(['100,2', '10,1', '3.5,0.5'])
            ->withDictionary([',', '0', '1', '00', '.'])
            ->libraryDir($library)
            ->crashDir($crashes)
            ->catchCrashes()
            ->run();
    })->toThrow(FuzzCrashException::class);
});
