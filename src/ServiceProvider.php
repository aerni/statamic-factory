<?php

namespace Aerni\Factory;

use Aerni\Factory\Commands\RunFactory;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    public function boot()
	{
		parent::boot();

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
