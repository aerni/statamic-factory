<?php

namespace Aerni\Factory\Tests;

use Aerni\Factory\Factories\Factory;
use Illuminate\Support\Collection;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Taxonomy as TaxonomyFacade;
use Statamic\Facades\Term as TermFacade;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class FactoryTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    protected function setUp(): void
    {
        parent::setUp();

        CollectionFacade::make('pages')->save();
        CollectionFacade::make('posts')->save();
        TaxonomyFacade::make('tags')->save();
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

        $entries = FactoryTestEntryFactory::new()->createMany();
        $this->assertInstanceOf(Collection::class, $entries);
        $this->assertCount(1, $entries);
        $this->assertInstanceOf(Entry::class, $entries->first());

        $entries = FactoryTestEntryFactory::new()->createMany(2);
        $this->assertInstanceOf(Collection::class, $entries);
        $this->assertCount(2, $entries);
        $this->assertInstanceOf(Entry::class, $entries->first());

        $entries = FactoryTestEntryFactory::new()->createMany([
            ['name' => 'Michael Aerni'],
            ['name' => 'Jack McDade'],
        ]);
        $this->assertInstanceOf(Collection::class, $entries);
        $this->assertCount(2, $entries);
        $this->assertInstanceOf(Entry::class, $entries->first());

        $entries = FactoryTestEntryFactory::times(2)->createMany();
        $this->assertInstanceOf(Collection::class, $entries);
        $this->assertCount(2, $entries);
        $this->assertInstanceOf(Entry::class, $entries->first());

        $entries = FactoryTestEntryFactory::times(3)->createMany([
            ['name' => 'Michael Aerni'],
            ['name' => 'Jack McDade'],
        ]);
        $this->assertInstanceOf(Collection::class, $entries);
        $this->assertCount(2, $entries);
        $this->assertInstanceOf(Entry::class, $entries->first());

        $entries = FactoryTestEntryFactory::times(10)->create();
        $this->assertInstanceOf(Collection::class, $entries);
        $this->assertCount(10, $entries);
        $this->assertInstanceOf(Entry::class, $entries->first());

        $entry = FactoryTestEntryFactory::times(3)->createOne();
        $this->assertInstanceOf(Entry::class, $entry);

        $entries = FactoryTestEntryFactory::new()->count(2)->create();
        $this->assertInstanceOf(Collection::class, $entries);
        $this->assertCount(2, $entries);
        $this->assertInstanceOf(Entry::class, $entries->first());

        $entry = FactoryTestEntryFactory::new()->count(2)->createOne();
        $this->assertInstanceOf(Entry::class, $entry);

        $entries = FactoryTestEntryFactory::new()->count(3)->createMany();
        $this->assertInstanceOf(Collection::class, $entries);
        $this->assertCount(3, $entries);
        $this->assertInstanceOf(Entry::class, $entries->first());

        $entries = FactoryTestEntryFactory::new()->count(3)->createMany([
            ['name' => 'Michael Aerni'],
            ['name' => 'Jack McDade'],
        ]);
        $this->assertInstanceOf(Collection::class, $entries);
        $this->assertCount(2, $entries);
        $this->assertInstanceOf(Entry::class, $entries->first());
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

    public function test_make_creates_unpersisted_model_instance()
    {
        $entry = FactoryTestEntryFactory::new()->makeOne();
        $this->assertInstanceOf(Entry::class, $entry);

        $entry = FactoryTestEntryFactory::new()->make(['name' => 'Michael Aerni']);

        $this->assertInstanceOf(Entry::class, $entry);
        $this->assertSame('Michael Aerni', $entry->name);
        $this->assertCount(0, EntryFacade::all());
    }

    public function test_basic_model_attributes_can_be_created()
    {
        $entry = FactoryTestEntryFactory::new()->raw();
        $this->assertIsArray($entry);

        $entry = FactoryTestEntryFactory::new()->raw(['name' => 'Michael Aerni']);
        $this->assertIsArray($entry);
        $this->assertSame('Michael Aerni', $entry['name']);
    }

    public function test_expanded_model_attributes_can_be_created()
    {
        $post = FactoryTestPostFactory::new()->raw();
        $this->assertIsArray($post);

        $post = FactoryTestPostFactory::new()->raw(['title' => 'Test Title']);
        $this->assertIsArray($post);
        $this->assertIsString($post['linked_entry']);
        $this->assertSame('Test Title', $post['title']);
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

class FactoryTestPostFactory extends Factory
{
    protected string $model = 'collections.posts';

    public function definition(): array
    {
        return [
            // 'user_id' => FactoryTestUserFactory::new(), // TODO: Add support for user factory.
            'title' => $this->faker->sentence(),
            'linked_entry' => FactoryTestEntryFactory::new(),
        ];
    }
}
