<?php

declare(strict_types=1);

namespace Fuzz\Runners;

use Closure;
use Fuzz\Exceptions\FuzzCrashException;
use Fuzz\Exceptions\FuzzException;
use Fuzz\Library\LibraryManager;
use Fuzz\Support\HexDumper;
use Fuzz\Support\TargetSerializer;
use Fuzz\ValueObjects\FuzzConfiguration;
use Fuzz\ValueObjects\FuzzResult;
use Symfony\Component\Process\Process;

final class ProcessRunner
{
    public function __construct(
        private readonly TargetSerializer $serializer = new TargetSerializer,
        private readonly LibraryManager $library = new LibraryManager,
        private readonly HexDumper $hexDumper = new HexDumper,
        private readonly ?string $phpBinary = null,
    ) {}

    public function run(Closure $target, FuzzConfiguration $configuration): FuzzResult
    {
        $basePath = getcwd() ?: throw new FuzzException('Unable to determine working directory.');
        $jobDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'fuzz-job-'.bin2hex(random_bytes(8));
        $this->library->ensureDirectory($jobDir);

        $libraryDir = $configuration->resolvedLibraryDir($basePath);
        $crashDir = $configuration->resolvedCrashDir($basePath);
        $this->library->ensureDirectory($libraryDir);
        $this->library->ensureDirectory($crashDir);

        if ($configuration->seeds !== []) {
            $this->library->materializeSeeds($libraryDir, $configuration->seeds);
        }

        $dictionaryPaths = $this->library->materializeDictionaries($jobDir, $configuration->dictionary);

        $configPayload = $configuration->toArray();
        $configPayload['libraryDir'] = $libraryDir;
        $configPayload['crashDir'] = $crashDir;
        $configPayload['dictionary'] = $dictionaryPaths;
        $configPayload['autoload'] = $basePath.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

        $configPath = $jobDir.DIRECTORY_SEPARATOR.'config.json';
        $targetPath = $jobDir.DIRECTORY_SEPARATOR.'target.ser';

        file_put_contents($configPath, json_encode($configPayload, JSON_THROW_ON_ERROR));
        $this->serializer->write($targetPath, $target);

        $worker = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'fuzz-worker.php';
        $php = $this->resolvePhpBinary();

        $process = new Process([$php, $worker, $jobDir], $basePath, null, null, null);
        $process->setTimeout(null);
        $process->run();

        $result = $this->parseResult($process->getOutput(), $process->getErrorOutput());

        $this->cleanupJob($jobDir);

        if ($result->crashed()) {
            $message = $this->formatCrashMessage($result);
            throw new FuzzCrashException($message);
        }

        if (! $process->isSuccessful() && $process->getExitCode() !== 1) {
            throw new FuzzException(
                'Fuzz worker failed: '.$process->getErrorOutput().$process->getOutput()
            );
        }

        return $result;
    }

    private function parseResult(string $stdout, string $stderr): FuzzResult
    {
        $lines = preg_split('/\R/', trim($stdout)) ?: [];
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = trim($lines[$i]);
            if ($line === '') {
                continue;
            }

            try {
                /** @var array<string, mixed> $decoded */
                $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }

            if (($decoded['type'] ?? null) === 'result') {
                return FuzzResult::fromArray($decoded);
            }
        }

        throw new FuzzException(
            "Fuzz worker did not return a result payload.\nSTDOUT:\n{$stdout}\nSTDERR:\n{$stderr}"
        );
    }

    private function formatCrashMessage(FuzzResult $result): string
    {
        $parts = [
            sprintf(
                'Fuzz crash after %d runs (features: %d)',
                $result->runs,
                $result->features,
            ),
        ];

        if ($result->exception !== null && $result->exception !== '') {
            $parts[] = 'Exception: '.$result->exception;
        }

        if ($result->crashPath !== null) {
            $parts[] = 'Crash saved: '.$result->crashPath;
        }

        if ($result->payload !== null) {
            $parts[] = '';
            $parts[] = $this->hexDumper->dump($result->payload);
        }

        return implode(PHP_EOL, $parts);
    }

    private function cleanupJob(string $jobDir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($jobDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }

        @rmdir($jobDir);
    }

    private function resolvePhpBinary(): string
    {
        if ($this->phpBinary !== null) {
            return $this->phpBinary;
        }

        // Prefer the current binary when it is already a supported 8.4.x runtime.
        if (PHP_VERSION_ID >= 80400 && PHP_VERSION_ID < 80500) {
            return PHP_BINARY;
        }

        // Prefer a non-8.5 binary when available: php-fuzzer converts deprecations to Error.
        // These Homebrew paths are Unix-only; probing them via shell_exec on Windows also
        // breaks because cmd.exe mangles PHP -r scripts that contain string concatenation.
        if (PHP_OS_FAMILY === 'Windows') {
            return PHP_BINARY;
        }

        $candidates = [
            '/opt/homebrew/Cellar/php-zts/8.4.12/bin/php',
            '/opt/homebrew/opt/php@8.4/bin/php',
        ];

        foreach ($candidates as $candidate) {
            if (! is_file($candidate)) {
                continue;
            }

            $version = trim((string) shell_exec(escapeshellarg($candidate).' -r '.escapeshellarg('echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')));
            if (version_compare($version, '8.5', '<') && version_compare($version, '8.4', '>=')) {
                return $candidate;
            }
        }

        return PHP_BINARY;
    }
}
