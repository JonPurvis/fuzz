<?php

declare(strict_types=1);

use Fuzz\Exceptions\FuzzCrashException;
use Tests\Fixtures\LeakySanitizer;

use function Fuzz\fuzz;

it('datasets stay green for known clean HTML', function (string $html): void {
    expect(fn () => LeakySanitizer::assertClean($html))->not->toThrow(Throwable::class);
})->with([
    'plain text' => ['hello'],
    'safe tags' => ['<b>hi</b>'],
]);

it('datasets regress known script-leak payloads', function (string $html): void {
    expect(fn () => LeakySanitizer::assertClean($html))->toThrow(Error::class);
})->with([
    'script alert' => ['<script>alert(1)</script>'],
    'uppercase-ish script' => ['<script src=x></script>'],
]);

it('fuzz finds script leaks around sanitizer seeds', function (): void {
    [$library, $crashes] = fuzzScratchDirs('fuzz-sanitizer');
    $dict = dirname(__DIR__).'/Fixtures/dictionaries/xss.dict';

    expect(function () use ($library, $crashes, $dict): void {
        fuzz(Closure::fromCallable([LeakySanitizer::class, 'assertClean']))
            ->runs(80)
            ->maxLen(64)
            ->seed(['hello', '<b>hi</b>', '<script>alert(1)</script>'])
            ->withDictionary([$dict])
            ->libraryDir($library)
            ->crashDir($crashes)
            ->catchCrashes()
            ->run();
    })->toThrow(FuzzCrashException::class);
});
