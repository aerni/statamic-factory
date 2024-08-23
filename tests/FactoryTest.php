<?php

namespace Aerni\Factory\Tests;

use Statamic\Facades\Taxonomy;
use Aerni\Factory\Factories\Factory;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term;

class FactoryTest extends TestCase
{
    public function test_basic_model_can_be_created(): void
    {
        $entry = FactoryTestEntryFactory::new()->make();
        $this->assertInstanceOf(Entry::class, $entry);

        $term = FactoryTestTermFactory::new()->make();
        $this->assertInstanceOf(Term::class, $term);
    }
}

class FactoryTestEntryFactory extends Factory
{
    protected string $model = 'collections.pages';

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
        ];
    }
}

class FactoryTestTermFactory extends Factory
{
    protected string $model = 'taxonomies.tags';

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
        ];
    }
}
