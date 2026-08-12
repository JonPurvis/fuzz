<?php

declare(strict_types=1);

use Fuzz\Exceptions\FuzzCrashException;
use Tests\Fixtures\WebhookPayloadParser;

use function Fuzz\fuzz;

it('datasets regress known crashing webhook payloads', function (string $json): void {
    expect(fn () => WebhookPayloadParser::parse($json))->toThrow(TypeError::class);
})->with([
    'empty object' => ['{}'],
    'null json' => ['null'],
    'array root' => ['[]'],
    'missing event' => ['{"type":"ping"}'],
]);

it('datasets stay green for known good webhook payloads', function (string $json): void {
    expect(WebhookPayloadParser::parse($json))->toBe('ping');
})->with([
    'simple event' => ['{"event":"ping"}'],
]);

it('fuzz finds hostile webhook JSON around seeds', function (): void {
    [$library, $crashes] = fuzzScratchDirs('fuzz-webhook');
    $dict = dirname(__DIR__).'/Fixtures/dictionaries/json.dict';

    expect(function () use ($library, $crashes, $dict): void {
        fuzz(Closure::fromCallable([WebhookPayloadParser::class, 'parse']))
            ->runs(150)
            ->maxLen(64)
            ->seed(['{"event":"ping"}', '{"event":"pong","id":1}'])
            ->withDictionary([$dict])
            ->libraryDir($library)
            ->crashDir($crashes)
            ->saveCrashes()
            ->run();
    })->toThrow(FuzzCrashException::class);
});
