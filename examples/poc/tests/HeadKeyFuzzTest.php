<?php

declare(strict_types=1);

use App\Support\HeadKey;
use Fuzz\Exceptions\FuzzCrashException;

use function Fuzz\fuzz;

it('datasets stay green for known good JSON', function (string $json): void {
    expect(HeadKey::parse($json))->toBeString();
})->with([
    '{"key":"a"}',
    '{"key":"hello"}',
]);

it('fuzz finds hostile JSON that datasets missed', function (): void {
    expect(function (): void {
        fuzz(Closure::fromCallable([HeadKey::class, 'parse']))
            ->seed(['{"key":"a"}'])
            ->withDictionary([__DIR__.'/../dictionaries/json.dict'])
            ->runs(500)
            ->maxLen(64)
            ->libraryDir(__DIR__.'/../.pest/fuzz-library/headkey')
            ->crashDir(__DIR__.'/../.pest/fuzz-crashes/headkey')
            ->catchCrashes()
            ->run();
    })->toThrow(FuzzCrashException::class);
});
