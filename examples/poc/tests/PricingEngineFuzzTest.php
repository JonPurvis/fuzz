<?php

declare(strict_types=1);

use App\Support\PricingEngine;
use Fuzz\Exceptions\FuzzCrashException;

use function Fuzz\fuzz;

it('datasets stay green for known good pricing quotes', function (string $input, float $expected): void {
    expect(PricingEngine::quote($input))->toBe($expected);
})->with([
    'half' => ['100,2', 50.0],
    'unity' => ['10,1', 10.0],
]);

it('datasets regress known crashing pricing quotes', function (string $input): void {
    expect(fn () => PricingEngine::quote($input))->toThrow(DivisionByZeroError::class);
})->with([
    'denominator zero' => ['100,0'],
]);

it('fuzz finds hostile pricing inputs that datasets missed', function (): void {
    expect(function (): void {
        fuzz(Closure::fromCallable([PricingEngine::class, 'quote']))
            ->seed(['100,2', '10,1'])
            ->withDictionary([',', '0', '1', '00'])
            ->runs(300)
            ->maxLen(32)
            ->libraryDir(__DIR__.'/../.pest/fuzz-library/pricing')
            ->crashDir(__DIR__.'/../.pest/fuzz-crashes/pricing')
            ->saveCrashes()
            ->run();
    })->toThrow(FuzzCrashException::class);
});
