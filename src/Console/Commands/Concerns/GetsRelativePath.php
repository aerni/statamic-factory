<?php

namespace Aerni\Factory\Console\Commands\Concerns;

trait GetsRelativePath
{
    protected function getRelativePath(string $path): string
    {
        return str_replace(base_path().'/', '', $path);
    }
}
