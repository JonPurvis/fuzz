<?php

declare(strict_types=1);

namespace Fuzz;

use Closure;

function fuzz(Closure $target): FuzzCall
{
    return new FuzzCall($target);
}
