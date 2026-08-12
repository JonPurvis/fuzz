<?php

declare(strict_types=1);

namespace Fuzz\Library;

use RuntimeException;

final class LibraryManager
{
    public function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (! mkdir($path, 0777, true) && ! is_dir($path)) {
            throw new RuntimeException("Unable to create directory [{$path}].");
        }
    }

    public function writeSeed(string $directory, string $contents, ?string $name = null): string
    {
        $this->ensureDirectory($directory);
        $hash = substr(hash('sha256', $contents), 0, 16);
        $filename = $name ?? "{$hash}.txt";
        $path = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$filename;

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException("Unable to write seed file [{$path}].");
        }

        return $path;
    }

    public function writeCrash(string $directory, string $contents): string
    {
        $hash = substr(hash('sha256', $contents), 0, 16);

        return $this->writeSeed($directory, $contents, "crash-{$hash}.txt");
    }

    /**
     * @param  list<string>  $seeds
     * @return list<string>
     */
    public function materializeSeeds(string $libraryDir, array $seeds): array
    {
        $this->ensureDirectory($libraryDir);
        $paths = [];

        foreach ($seeds as $index => $seed) {
            if (is_file($seed)) {
                $contents = file_get_contents($seed);
                if ($contents === false) {
                    throw new RuntimeException("Unable to read seed file [{$seed}].");
                }
                $paths[] = $this->writeSeed($libraryDir, $contents);
            } else {
                $paths[] = $this->writeSeed($libraryDir, $seed, sprintf('seed-%d-%s.txt', $index, substr(hash('sha256', $seed), 0, 8)));
            }
        }

        return $paths;
    }

    /**
     * @param  list<string>  $entries  file paths and/or raw keyword strings
     * @return list<string> absolute dictionary file paths
     */
    public function materializeDictionaries(string $jobDir, array $entries): array
    {
        $this->ensureDirectory($jobDir);
        $paths = [];
        $inline = [];

        foreach ($entries as $entry) {
            if (is_file($entry)) {
                $paths[] = realpath($entry) ?: $entry;
            } else {
                $inline[] = $entry;
            }
        }

        if ($inline !== []) {
            $lines = array_map(
                static fn (string $word): string => '"'.addcslashes($word, "\\\"\n\r").'"',
                $inline,
            );
            $path = rtrim($jobDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'inline.dict';
            if (file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL) === false) {
                throw new RuntimeException("Unable to write dictionary file [{$path}].");
            }
            $paths[] = $path;
        }

        return $paths;
    }
}
