<?php

namespace Aerni\Factory;

use Faker\Factory;
use Faker\Generator;
use Smknstd\FakerPicsumImages\FakerPicsumImagesProvider;
use Statamic\Providers\AddonServiceProvider;

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
