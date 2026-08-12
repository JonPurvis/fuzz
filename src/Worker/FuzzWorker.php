<?php

declare(strict_types=1);

namespace Fuzz\Worker;

use Closure;
use Fuzz\Exceptions\FuzzException;
use Fuzz\Library\LibraryManager;
use Fuzz\Support\TargetSerializer;
use Fuzz\ValueObjects\FuzzConfiguration;
use Fuzz\ValueObjects\FuzzResult;
use PhpFuzzer\Config;
use PhpFuzzer\Corpus;
use PhpFuzzer\Fuzzer;
use PHPUnit\Framework\ExpectationFailedException;
use ReflectionClass;
use ReflectionMethod;

final class FuzzWorker
{
    public function __construct(
        private readonly TargetSerializer $serializer = new TargetSerializer,
        private readonly LibraryManager $library = new LibraryManager,
    ) {}

    public function run(string $jobDir): int
    {
        $configPath = $jobDir.DIRECTORY_SEPARATOR.'config.json';
        $targetPath = $jobDir.DIRECTORY_SEPARATOR.'target.ser';

        $raw = file_get_contents($configPath);
        if ($raw === false) {
            throw new FuzzException("Missing config at [{$configPath}].");
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $configuration = FuzzConfiguration::fromArray($data);

        $autoload = isset($data['autoload']) && is_string($data['autoload']) ? $data['autoload'] : null;
        if ($autoload !== null && is_file($autoload)) {
            require_once $autoload;
        }

        if (! isset($data['libraryDir']) || ! is_string($data['libraryDir'])) {
            throw new FuzzException('libraryDir missing.');
        }
        if (! isset($data['crashDir']) || ! is_string($data['crashDir'])) {
            throw new FuzzException('crashDir missing.');
        }

        $libraryDir = $data['libraryDir'];
        $crashDir = $data['crashDir'];
        $this->library->ensureDirectory($libraryDir);
        $this->library->ensureDirectory($crashDir);

        $dictionaryPaths = [];
        if (isset($data['dictionary']) && is_array($data['dictionary'])) {
            foreach ($data['dictionary'] as $path) {
                if (is_string($path) && $path !== '') {
                    $dictionaryPaths[] = $path;
                }
            }
        }
        $userTarget = $this->serializer->read($targetPath);
        $wrapped = $this->wrapTarget($userTarget);

        $fuzzer = new Fuzzer;
        $ref = new ReflectionClass(Fuzzer::class);

        /** @var Config $config */
        $config = $this->property($ref, $fuzzer, 'config');
        $config->setTarget($wrapped);
        $config->setMaxLen($configuration->maxLen);
        $config->setAllowedExceptions($configuration->allowedExceptions);

        foreach ($dictionaryPaths as $dictionaryPath) {
            $config->addDictionary($dictionaryPath);
        }

        $this->setProperty($ref, $fuzzer, 'maxRuns', $configuration->runs);
        $this->setProperty($ref, $fuzzer, 'timeout', $configuration->timeout);
        $this->setProperty($ref, $fuzzer, 'maxCrashes', 1);
        $this->setProperty($ref, $fuzzer, 'outputDir', $crashDir);

        $fuzzer->startInstrumentation();
        $this->invoke($ref, $fuzzer, 'setupTimeoutHandler');
        $this->invoke($ref, $fuzzer, 'setupErrorHandler');
        $this->invoke($ref, $fuzzer, 'setupShutdownHandler');

        $fuzzer->setCorpusDir($libraryDir);

        // Capture crash text printed by php-fuzzer while still collecting structured result.
        ob_start();
        try {
            $fuzzer->fuzz();
        } finally {
            $printed = ob_get_clean() ?: '';
        }

        /** @var Corpus $corpus */
        $corpus = $this->property($ref, $fuzzer, 'corpus');
        $runsValue = $this->property($ref, $fuzzer, 'runs');
        $runs = is_int($runsValue) ? $runsValue : 0;
        $crashPath = $this->latestCrashFile($crashDir);
        $crashed = $crashPath !== null || str_contains($printed, 'CRASH');

        if ($crashed) {
            $payload = $crashPath !== null ? (file_get_contents($crashPath) ?: '') : null;
            $exception = $this->extractCrashMessage($printed);

            // CORPUS CRASH / some crash paths print "CRASH in {path}!" without writing crash-*.txt.
            if (($payload === null || $payload === '') && preg_match('/^(?:CORPUS )?CRASH in (.+)!/m', $printed, $matches) === 1) {
                $sourcePath = $matches[1];
                if (is_file($sourcePath)) {
                    $payload = file_get_contents($sourcePath) ?: '';
                }
            }

            if ($crashPath === null && $configuration->saveCrashes && is_string($payload) && $payload !== '') {
                $crashPath = $this->library->writeCrash($crashDir, $payload);
            }

            if (! $configuration->saveCrashes && $crashPath !== null) {
                @unlink($crashPath);
                $crashPath = null;
            }

            $result = new FuzzResult(
                crashed: true,
                runs: $runs,
                features: $corpus->getNumFeatures(),
                librarySize: $corpus->getNumCorpusEntries(),
                payload: $payload,
                exception: $exception,
                crashPath: $crashPath,
                message: 'CRASH',
            );
            $this->emit($result);

            return 1;
        }

        $result = FuzzResult::success($runs, $corpus->getNumFeatures(), $corpus->getNumCorpusEntries());
        $this->emit($result);

        return 0;
    }

    private function wrapTarget(Closure $userTarget): Closure
    {
        return static function (string $input) use ($userTarget): void {
            try {
                $userTarget($input);
            } catch (ExpectationFailedException $exception) {
                throw new \Error($exception->getMessage(), 0, $exception);
            }
        };
    }

    private function latestCrashFile(string $crashDir): ?string
    {
        $files = glob(rtrim($crashDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'crash-*.txt') ?: [];
        if ($files === []) {
            return null;
        }

        usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $files[0];
    }

    private function extractCrashMessage(string $printed): ?string
    {
        $printed = trim($printed);
        if ($printed === '') {
            return null;
        }

        $lines = preg_split('/\R/', $printed) ?: [];
        // php-fuzzer prints "CRASH in path!" then the exception string.
        $buffer = [];
        $capture = false;
        foreach ($lines as $line) {
            if (str_starts_with($line, 'CRASH') || str_starts_with($line, 'CORPUS CRASH')) {
                $capture = true;

                continue;
            }
            if ($capture) {
                $buffer[] = $line;
            }
        }

        $message = trim(implode(PHP_EOL, $buffer));

        return $message !== '' ? $message : $printed;
    }

    private function emit(FuzzResult $result): void
    {
        echo json_encode($result->toArray(), JSON_THROW_ON_ERROR), PHP_EOL;
    }

    /**
     * @param  ReflectionClass<Fuzzer>  $ref
     * @param  list<mixed>  $args
     */
    private function invoke(ReflectionClass $ref, Fuzzer $object, string $method, array $args = []): mixed
    {
        $methodRef = new ReflectionMethod($object, $method);

        return $methodRef->invokeArgs($object, $args);
    }

    /**
     * @param  ReflectionClass<Fuzzer>  $ref
     */
    private function property(ReflectionClass $ref, Fuzzer $object, string $name): mixed
    {
        return $ref->getProperty($name)->getValue($object);
    }

    /**
     * @param  ReflectionClass<Fuzzer>  $ref
     */
    private function setProperty(ReflectionClass $ref, Fuzzer $object, string $name, mixed $value): void
    {
        $ref->getProperty($name)->setValue($object, $value);
    }
}
