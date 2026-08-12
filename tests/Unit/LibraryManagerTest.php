<?php

declare(strict_types=1);

use Fuzz\Library\LibraryManager;

it('writes crash seeds with crash- prefix', function (): void {
    $dir = sys_get_temp_dir().'/fuzz-lib-'.bin2hex(random_bytes(4));
    $manager = new LibraryManager;

    $path = $manager->writeCrash($dir, '{"broken":true}');

    expect($path)->toContain('crash-')
        ->and(file_get_contents($path))->toBe('{"broken":true}');
});

it('materializes inline and file seeds into the library', function (): void {
    $dir = sys_get_temp_dir().'/fuzz-seeds-'.bin2hex(random_bytes(4));
    $file = sys_get_temp_dir().'/seed-file-'.bin2hex(random_bytes(4)).'.txt';
    file_put_contents($file, 'from-file');

    $manager = new LibraryManager;
    $paths = $manager->materializeSeeds($dir, ['inline-seed', $file]);

    expect($paths)->toHaveCount(2)
        ->and(file_get_contents($paths[0]))->toBe('inline-seed')
        ->and(file_get_contents($paths[1]))->toBe('from-file');
});

it('materializes dictionary file paths alongside inline keywords', function (): void {
    $jobDir = sys_get_temp_dir().'/fuzz-dict-job-'.bin2hex(random_bytes(4));
    $dictFile = dirname(__DIR__).'/Fixtures/dictionaries/json.dict';

    $manager = new LibraryManager;
    $paths = $manager->materializeDictionaries($jobDir, [$dictFile, '{', 'null']);

    expect($paths)->toHaveCount(2)
        ->and($paths[0])->toBe(realpath($dictFile) ?: $dictFile)
        ->and(file_get_contents($paths[1]))->toContain('"{"')
        ->and(file_get_contents($paths[1]))->toContain('"null"');
});

it('creates missing directories when writing seeds', function (): void {
    $dir = sys_get_temp_dir().'/fuzz-ensure-'.bin2hex(random_bytes(4)).'/nested';
    $manager = new LibraryManager;

    $path = $manager->writeSeed($dir, 'nested-seed');

    expect(is_dir($dir))->toBeTrue()
        ->and(file_get_contents($path))->toBe('nested-seed');
});
