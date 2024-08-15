<?php

namespace Aerni\Factory\Factories;

use Closure;

trait DefinitionHelpers
{
    public function repeat(int $count, Closure $callback): array
    {
        return array_map(fn () => $callback(), range(1, $count));
    }
}
