<?php

namespace Aerni\Factory\Factories;

class UserFactoryDefinitionGenerator extends FactoryDefinitionGenerator
{
    protected function defaults(): array
    {
        return [
            'email' => '$this->faker->unique()->safeEmail',
            'password' => '$this->faker->password',
            'super' => 'false',
        ];
    }
}
