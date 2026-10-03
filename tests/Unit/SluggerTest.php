<?php

declare(strict_types=1);

use Tests\Fixtures\Slugger;

use function Fuzz\fuzz;

$target = static function (string $input): void {
    Slugger::make($input);
};

test('slugger must not contain spaces', function () use ($target): void {
    fuzz($target)
        ->seed([
            'Hello World',
            'Laravel is Awesome !',
            'A blog post updated',
        ])
        ->withDictionary([
            ' ',
            '/',
            '-',
            '.',
            '_',
        ])
        ->runs(2000)
        ->maxLen(16)
        ->run('slugger');
});
