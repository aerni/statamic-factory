<?php

namespace Aerni\Factory;

use Faker\Factory;
use Faker\Generator;
use Statamic\Providers\AddonServiceProvider;
use Smknstd\FakerPicsumImages\FakerPicsumImagesProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $commands = [
        Console\Commands\MakeFactory::class,
        Console\Commands\MakeSeeder::class,
        Console\Commands\Seed::class,
    ];

    public function register(): void
    {
        $this->app->singleton(Generator::class, function () {
            $faker = Factory::create();

            $faker->addProvider(new FakerPicsumImagesProvider($faker));

            return $faker;
        });
    }
}
