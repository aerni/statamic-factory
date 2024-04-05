<?php

namespace Aerni\Factory\Factories;

use Aerni\Factory\Contracts\Factory;
use Aerni\Factory\Faker;
use Statamic\Globals\GlobalSet;

class GlobalFactory implements Factory
{
    protected Faker $faker;

    public function __construct(protected GlobalSet $global)
    {
        $this->faker = app(Faker::class, ['blueprint' => $this->global->blueprint()]);
    }

    public function run(): void
    {
        $this->global
            ->inDefaultSite()
            ->data($this->faker->data())
            ->save();
    }
}
