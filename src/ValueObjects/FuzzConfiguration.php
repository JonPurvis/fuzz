<?php

declare(strict_types=1);

namespace Fuzz\ValueObjects;

/**
 * @phpstan-type AllowedExceptionList list<class-string<\Throwable>>
 */
final class FuzzConfiguration
{
    public const DEFAULT_RUNS = 1000;

    public const DEFAULT_MAX_LEN = 4096;

    public const DEFAULT_TIMEOUT = 3;

    /**
     * @param  list<string>  $dictionary
     * @param  list<string>  $seeds
     * @param  AllowedExceptionList  $allowedExceptions
     */
    public function __construct(
        public int $runs = self::DEFAULT_RUNS,
        public int $maxLen = self::DEFAULT_MAX_LEN,
        public int $timeout = self::DEFAULT_TIMEOUT,
        public array $dictionary = [],
        public array $seeds = [],
        public ?string $libraryDir = null,
        public ?string $crashDir = null,
        public bool $catchCrashes = true,
        public array $allowedExceptions = [\Exception::class],
        public string $description = 'fuzz',
    ) {}

    public function withRuns(int $runs): self
    {
        $clone = clone $this;
        $clone->runs = $runs;

        return $clone;
    }

    public function withMaxLen(int $maxLen): self
    {
        $clone = clone $this;
        $clone->maxLen = $maxLen;

        return $clone;
    }

    public function withTimeout(int $timeout): self
    {
        $clone = clone $this;
        $clone->timeout = $timeout;

        return $clone;
    }

    /**
     * @param  list<string>  $dictionary
     */
    public function withDictionary(array $dictionary): self
    {
        $clone = clone $this;
        $clone->dictionary = $dictionary;

        return $clone;
    }

    /**
     * @param  list<string>  $seeds
     */
    public function withSeeds(array $seeds): self
    {
        $clone = clone $this;
        $clone->seeds = $seeds;

        return $clone;
    }

    public function withLibraryDir(string $libraryDir): self
    {
        $clone = clone $this;
        $clone->libraryDir = $libraryDir;

        return $clone;
    }

    public function withCrashDir(string $crashDir): self
    {
        $clone = clone $this;
        $clone->crashDir = $crashDir;

        return $clone;
    }

    public function withCatchCrashes(bool $catchCrashes): self
    {
        $clone = clone $this;
        $clone->catchCrashes = $catchCrashes;

        return $clone;
    }

    /**
     * @param  AllowedExceptionList  $allowedExceptions
     */
    public function withAllowedExceptions(array $allowedExceptions): self
    {
        $clone = clone $this;
        $clone->allowedExceptions = $allowedExceptions;

        return $clone;
    }

    public function withDescription(string $description): self
    {
        $clone = clone $this;
        $clone->description = $description;

        return $clone;
    }

    public function testHash(): string
    {
        return substr(hash('sha256', $this->description), 0, 12);
    }

    public function resolvedLibraryDir(string $basePath): string
    {
        if ($this->libraryDir !== null) {
            return $this->isAbsolutePath($this->libraryDir)
                ? $this->libraryDir
                : $basePath.DIRECTORY_SEPARATOR.$this->libraryDir;
        }

        return $basePath.DIRECTORY_SEPARATOR.'.pest'.DIRECTORY_SEPARATOR.'fuzz-library'.DIRECTORY_SEPARATOR.$this->testHash();
    }

    public function resolvedCrashDir(string $basePath): string
    {
        if ($this->crashDir !== null) {
            return $this->isAbsolutePath($this->crashDir)
                ? $this->crashDir
                : $basePath.DIRECTORY_SEPARATOR.$this->crashDir;
        }

        return $basePath.DIRECTORY_SEPARATOR.'.pest'.DIRECTORY_SEPARATOR.'fuzz-crashes'.DIRECTORY_SEPARATOR.$this->testHash();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'runs' => $this->runs,
            'maxLen' => $this->maxLen,
            'timeout' => $this->timeout,
            'dictionary' => $this->dictionary,
            'seeds' => $this->seeds,
            'libraryDir' => $this->libraryDir,
            'crashDir' => $this->crashDir,
            'catchCrashes' => $this->catchCrashes,
            'allowedExceptions' => $this->allowedExceptions,
            'description' => $this->description,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var AllowedExceptionList $allowed */
        $allowed = is_array($data['allowedExceptions'] ?? null)
            ? $data['allowedExceptions']
            : [\Exception::class];

        $dictionary = [];
        if (isset($data['dictionary']) && is_array($data['dictionary'])) {
            foreach ($data['dictionary'] as $entry) {
                if (is_string($entry)) {
                    $dictionary[] = $entry;
                }
            }
        }

        $seeds = [];
        if (isset($data['seeds']) && is_array($data['seeds'])) {
            foreach ($data['seeds'] as $entry) {
                if (is_string($entry)) {
                    $seeds[] = $entry;
                }
            }
        }

        return new self(
            runs: is_int($data['runs'] ?? null) ? $data['runs'] : self::DEFAULT_RUNS,
            maxLen: is_int($data['maxLen'] ?? null) ? $data['maxLen'] : self::DEFAULT_MAX_LEN,
            timeout: is_int($data['timeout'] ?? null) ? $data['timeout'] : self::DEFAULT_TIMEOUT,
            dictionary: $dictionary,
            seeds: $seeds,
            libraryDir: isset($data['libraryDir']) && is_string($data['libraryDir']) ? $data['libraryDir'] : null,
            crashDir: isset($data['crashDir']) && is_string($data['crashDir']) ? $data['crashDir'] : null,
            catchCrashes: is_bool($data['catchCrashes'] ?? null) ? $data['catchCrashes'] : true,
            allowedExceptions: $allowed,
            description: isset($data['description']) && is_string($data['description']) ? $data['description'] : 'fuzz',
        );
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || (bool) preg_match('#^[A-Za-z]:[\\\\/]#', $path);
    }
}
