<?php

declare(strict_types=1);

namespace Fuzz\Exceptions;

use PHPUnit\Framework\AssertionFailedError;

final class FuzzCrashException extends AssertionFailedError {}
