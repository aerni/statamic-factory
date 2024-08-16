<?php

namespace Aerni\Factory;

use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $commands = [
        Console\Commands\MakeFactory::class,
        Console\Commands\MakeSeeder::class,
        Console\Commands\Seed::class,
    ];
}
