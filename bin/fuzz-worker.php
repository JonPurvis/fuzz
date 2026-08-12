#!/usr/bin/env php
<?php

declare(strict_types=1);

use Fuzz\Exceptions\FuzzException;
use Fuzz\Worker\FuzzWorker;

$autoloadCandidates = [
    dirname(__DIR__).'/vendor/autoload.php',
    dirname(__DIR__, 3).'/autoload.php',
    dirname(__DIR__, 4).'/autoload.php',
];

$autoload = null;
foreach ($autoloadCandidates as $candidate) {
    if (is_file($candidate)) {
        $autoload = $candidate;
        break;
    }
}

if ($autoload === null) {
    fwrite(STDERR, "Unable to locate composer autoload.php\n");
    exit(2);
}

require $autoload;

$jobDir = $argv[1] ?? null;
if ($jobDir === null || ! is_dir($jobDir)) {
    fwrite(STDERR, "Usage: fuzz-worker.php <job-dir>\n");
    exit(2);
}

try {
    exit((new FuzzWorker)->run($jobDir));
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage().PHP_EOL.$throwable->getTraceAsString().PHP_EOL);

    $payload = [
        'type' => 'result',
        'crashed' => false,
        'runs' => 0,
        'features' => 0,
        'librarySize' => 0,
        'payload' => null,
        'exception' => $throwable->getMessage(),
        'crashPath' => null,
        'message' => 'worker-error',
    ];
    echo json_encode($payload, JSON_THROW_ON_ERROR), PHP_EOL;

    exit($throwable instanceof FuzzException ? 2 : 2);
}
