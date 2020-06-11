<?php

namespace Aerni\Factory;

use Aerni\Factory\Commands\RunFactory;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    public function boot()
    {
        parent::boot();

        $this->mergeConfigFrom(__DIR__.'/../config/factory.php', 'factory');

        $this->publishes([
            __DIR__.'/../config/factory.php' => config_path('factory.php'),
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                RunFactory::class,
            ]);
        }
    }

    public function register()
    {
        parent::register();
    }
}
