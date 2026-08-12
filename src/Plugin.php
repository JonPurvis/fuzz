<?php

declare(strict_types=1);

namespace Fuzz;

use Pest\Contracts\Plugins\HandlesOriginalArguments;

/**
 * @internal
 */
final class Plugin implements HandlesOriginalArguments
{
    /**
     * {@inheritdoc}
     *
     * @param  array<int, string>  $arguments
     */
    public function handleOriginalArguments(array $arguments): void
    {
        // Reserved for future CLI helpers (e.g. crash replay).
    }
}
