<?php

namespace Aerni\Factory\Console\Commands\Concerns;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

trait SavesFile
{
    protected function saveFile(string $path, string $contents): void
    {
        File::ensureDirectoryExists(dirname($path));

        File::put($path, $contents);

        Process::run('./vendor/bin/pint '.$path);
    }
}
