<?php

namespace Aerni\Factory\Console\Commands\Concerns;

use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

trait ReadsClassFromFile
{
    protected function getClassName(SplFileInfo $file): string
    {
        $contents = file_get_contents($file->getRealPath());

        preg_match('/namespace\s+([^;]+);/', $contents, $namespace);
        preg_match('/class\s+(\w+)/', $contents, $class);

        return $namespace[1].'\\'.$class[1];
    }

    protected function getClassDisplay(SplFileInfo $file): string
    {
        return Str::of($file->getRelativePathname())
            ->remove('.php');
    }
}
