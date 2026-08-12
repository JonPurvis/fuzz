<?php

declare(strict_types=1);

namespace Fuzz\Contracts;

interface FuzzTargetInterface
{
    public function __invoke(string $input): void;
}
