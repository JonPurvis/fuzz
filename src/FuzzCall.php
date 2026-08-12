<?php

declare(strict_types=1);

namespace Fuzz;

use Closure;
use Fuzz\Runners\ProcessRunner;
use Fuzz\ValueObjects\FuzzConfiguration;
use Fuzz\ValueObjects\FuzzResult;

final class FuzzCall
{
    private FuzzConfiguration $configuration;

    public function __construct(
        private readonly Closure $target,
        ?FuzzConfiguration $configuration = null,
    ) {
        $this->configuration = $configuration ?? new FuzzConfiguration;
    }

    public function runs(int $runs): self
    {
        $this->configuration = $this->configuration->withRuns($runs);

        return $this;
    }

    public function maxLen(int $bytes): self
    {
        $this->configuration = $this->configuration->withMaxLen($bytes);

        return $this;
    }

    public function timeout(int $seconds): self
    {
        $this->configuration = $this->configuration->withTimeout($seconds);

        return $this;
    }

    /**
     * @param  list<string>  $dictionary
     */
    public function withDictionary(array $dictionary): self
    {
        $this->configuration = $this->configuration->withDictionary($dictionary);

        return $this;
    }

    /**
     * @param  list<string>  $seeds
     */
    public function seed(array $seeds): self
    {
        $this->configuration = $this->configuration->withSeeds($seeds);

        return $this;
    }

    public function libraryDir(string $path): self
    {
        $this->configuration = $this->configuration->withLibraryDir($path);

        return $this;
    }

    public function crashDir(string $path): self
    {
        $this->configuration = $this->configuration->withCrashDir($path);

        return $this;
    }

    public function saveCrashes(bool $enabled = true): self
    {
        $this->configuration = $this->configuration->withSaveCrashes($enabled);

        return $this;
    }

    /**
     * @param  list<class-string<\Throwable>>  $exceptionClasses
     */
    public function allow(array $exceptionClasses): self
    {
        $this->configuration = $this->configuration->withAllowedExceptions($exceptionClasses);

        return $this;
    }

    public function configuration(): FuzzConfiguration
    {
        return $this->configuration;
    }

    public function run(?string $description = null): FuzzResult
    {
        if ($description !== null) {
            $this->configuration = $this->configuration->withDescription($description);
        }

        return (new ProcessRunner)->run($this->target, $this->configuration);
    }
}
