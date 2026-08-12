<?php

declare(strict_types=1);

use Fuzz\Exceptions\FuzzCrashException;
use Tests\Fixtures\InvalidTokenException;
use Tests\Fixtures\JwtVerifier;

use function Fuzz\fuzz;

it('datasets regress empty tokens as domain exceptions', function (): void {
    expect(fn () => JwtVerifier::verify(''))->toThrow(InvalidTokenException::class);
});

it('datasets regress known crashing garbage tokens', function (string $token): void {
    expect(fn () => JwtVerifier::verify($token))->toThrow(TypeError::class);
})->with([
    'single segment' => ['not-a-jwt'],
    'two segments' => ['aaa.bbb'],
    'bad base64 header' => ['!!!.bbb.ccc'],
]);

it('datasets stay green for a minimally valid looking token header', function (): void {
    $header = base64_encode('{"alg":"none","typ":"JWT"}');
    $token = $header.'.payload.sig';

    expect(JwtVerifier::verify($token))->toBe('none');
});

it('fuzz finds hostile JWT inputs while allowing empty-token domain exceptions', function (): void {
    [$library, $crashes] = fuzzScratchDirs('fuzz-jwt');
    $header = base64_encode('{"alg":"none","typ":"JWT"}');

    expect(function () use ($library, $crashes, $header): void {
        fuzz(Closure::fromCallable([JwtVerifier::class, 'verify']))
            ->runs(150)
            ->maxLen(64)
            ->seed([$header.'.payload.sig', 'a.b.c'])
            ->withDictionary(['.', '{', '}', 'alg', 'typ', 'null'])
            ->allow([InvalidTokenException::class])
            ->libraryDir($library)
            ->crashDir($crashes)
            ->catchCrashes()
            ->run();
    })->toThrow(FuzzCrashException::class);
});
