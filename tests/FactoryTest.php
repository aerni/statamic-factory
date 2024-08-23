<?php

namespace Aerni\Factory\Tests;

use Aerni\Factory\Factories\Factory;
use Illuminate\Support\Collection as LaravelCollection;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term as TermFacade;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class FactoryTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    protected function setUp(): void
    {
        parent::setUp();

        Collection::make('pages')->save();
        Taxonomy::make('tags')->save();
    }

    public function test_basic_model_can_be_created(): void
    {
        $entry = FactoryTestEntryFactory::new()->create();
        $this->assertInstanceOf(Entry::class, $entry);

        $entry = FactoryTestEntryFactory::new()->createOne();
        $this->assertInstanceOf(Entry::class, $entry);

        $entry = FactoryTestEntryFactory::new()->create(['name' => 'Michael Aerni']);
        $this->assertInstanceOf(Entry::class, $entry);
        $this->assertSame('Michael Aerni', $entry->name);

        $entry = FactoryTestEntryFactory::new()->set('name', 'Michael Aerni')->create();
        $this->assertInstanceOf(Entry::class, $entry);
        $this->assertSame('Michael Aerni', $entry->name);

        $entries = FactoryTestEntryFactory::new()->createMany([
            ['name' => 'Michael Aerni'],
            ['name' => 'Jack McDade'],
        ]);
        $this->assertInstanceOf(LaravelCollection::class, $entries);
        $this->assertCount(2, $entries);

        $entries = FactoryTestEntryFactory::new()->createMany(2);
        $this->assertInstanceOf(LaravelCollection::class, $entries);
        $this->assertCount(2, $entries);
        $this->assertInstanceOf(Entry::class, $entries->first());

        // TODO: This one somehow creates a nested collection. Need to investigate.
        // $entries = FactoryTestEntryFactory::times(2)->createMany();
        // $this->assertInstanceOf(LaravelCollection::class, $entries);
        // $this->assertCount(2, $entries);
        // $this->assertInstanceOf(Entry::class, $entries->first());

        $entries = FactoryTestEntryFactory::new()->count(2)->create();
        $this->assertInstanceOf(LaravelCollection::class, $entries);
        $this->assertCount(2, $entries);

        $entry = FactoryTestEntryFactory::new()->count(2)->createOne();
        $this->assertInstanceOf(Entry::class, $entry);

        $entries = FactoryTestEntryFactory::times(3)->create();
        $this->assertInstanceOf(LaravelCollection::class, $entries);
        $this->assertCount(3, $entries);

        $entry = FactoryTestEntryFactory::times(3)->createOne();
        $this->assertInstanceOf(Entry::class, $entry);

        $entries = FactoryTestEntryFactory::times(1)->create();
        $this->assertInstanceOf(LaravelCollection::class, $entries);
        $this->assertCount(1, $entries);
    }

    public function test_entry_can_be_created(): void
    {
        $entry = FactoryTestEntryFactory::new()->create();
        $this->assertInstanceOf(Entry::class, $entry);
        $this->assertNotNull(EntryFacade::find($entry->id));
    }

    public function test_term_can_be_created(): void
    {
        $term = FactoryTestTermFactory::new()->create();
        $this->assertInstanceOf(Term::class, $term);
        $this->assertNotNull(TermFacade::find($term->id));
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
