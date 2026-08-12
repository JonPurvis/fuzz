<?php

declare(strict_types=1);

use Pest\Expectation;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function (): Expectation {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Unique temp library + crash directories for an isolated fuzz run.
 *
 * @return array{0: string, 1: string}
 */
function fuzzScratchDirs(string $prefix = 'fuzz'): array
{
    $id = bin2hex(random_bytes(4));

    return [
        sys_get_temp_dir()."/{$prefix}-lib-{$id}",
        sys_get_temp_dir()."/{$prefix}-crash-{$id}",
    ];
}
