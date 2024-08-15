<?php

namespace Aerni\Factory;

use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $commands = [
        Commands\MakeFactory::class,
        Commands\RunFactory::class,
    ];
}
