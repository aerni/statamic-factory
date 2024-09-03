<?php

namespace Aerni\Factory\Tests;

use Aerni\Factory\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\CrossJoinSequence;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use ReflectionClass;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy as TaxonomyFacade;
use Statamic\Facades\Term as TermFacade;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class FactoryTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('statamic.system.multisite', true);

        Site::setSites([
            'default' => [
                'name' => 'English',
                'url' => '/',
                'locale' => 'en_US',
            ],
            'german' => [
                'name' => 'German',
                'url' => '/de/',
                'locale' => 'de_DE',
            ],
        ])->save();

        CollectionFacade::make('pages')->sites(['default', 'german'])->save();
        CollectionFacade::make('posts')->sites(['default', 'german'])->save();
        TaxonomyFacade::make('tags')->sites(['default', 'german'])->save();
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
        $this->assertNotNull(EntryFacade::find($entry->id()));
    }

    public function test_term_can_be_created(): void
    {
        $term = FactoryTestTermFactory::new()->create();
        $this->assertInstanceOf(Term::class, $term);
        $this->assertNotNull(TermFacade::find($term->id()));
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

    public function test_lazy_model_attributes_can_be_created()
    {
        $entryFunction = FactoryTestEntryFactory::new()->lazy();
        $this->assertIsCallable($entryFunction);
        $this->assertInstanceOf(Entry::class, $entryFunction());

        $entryFunction = FactoryTestEntryFactory::new()->lazy(['name' => 'Michael Aerni']);
        $this->assertIsCallable($entryFunction);

        $entry = $entryFunction();
        $this->assertInstanceOf(Entry::class, $entry);
        $this->assertSame('Michael Aerni', $entry->name);
    }

    public function test_multiple_model_attributes_can_be_created()
    {
        $posts = FactoryTestPostFactory::new()->times(10)->raw();
        $this->assertIsArray($posts);

        $this->assertCount(10, $posts);
    }

    public function test_after_creating_and_making_callbacks_are_called()
    {
        $entry = FactoryTestEntryFactory::new()
            ->afterMaking(function ($entry) {
                $_SERVER['__test.entry.making'] = $entry;
            })
            ->afterCreating(function ($entry) {
                $_SERVER['__test.entry.creating'] = $entry;
            })
            ->create();

        $this->assertSame($entry, $_SERVER['__test.entry.making']);
        $this->assertSame($entry, $_SERVER['__test.entry.creating']);

        unset($_SERVER['__test.entry.making'], $_SERVER['__test.entry.creating']);
    }

    public function test_sequences()
    {
        $entries = FactoryTestEntryFactory::times(2)->sequence(
            ['name' => 'Michael Aerni'],
            ['name' => 'Jack McDade'],
        )->create();

        $this->assertSame('Michael Aerni', $entries[0]->name);
        $this->assertSame('Jack McDade', $entries[1]->name);

        // TODO: These test won't work because we don't support relationships yet.
        // $user = FactoryTestUserFactory::new()
        //     ->hasAttached(
        //         FactoryTestRoleFactory::times(4),
        //         new Sequence(['admin' => 'Y'], ['admin' => 'N']),
        //         'roles'
        //     )
        //     ->create();

        // $this->assertCount(4, $user->roles);

        // $this->assertCount(2, $user->roles->filter(function ($role) {
        //     return $role->pivot->admin === 'Y';
        // }));

        // $this->assertCount(2, $user->roles->filter(function ($role) {
        //     return $role->pivot->admin === 'N';
        // }));

        $entries = FactoryTestEntryFactory::times(2)->sequence(function ($sequence) {
            return ['name' => 'index: '.$sequence->index];
        })->create();

        $this->assertSame('index: 0', $entries[0]->name);
        $this->assertSame('index: 1', $entries[1]->name);
    }

    public function test_counted_sequence()
    {
        $factory = FactoryTestEntryFactory::new()->forEachSequence(
            ['name' => 'Michael Aerni'],
            ['name' => 'Jack McDade'],
            ['name' => 'Jesse Leite'],
        );

        $class = new ReflectionClass($factory);
        $prop = $class->getProperty('count');
        $value = $prop->getValue($factory);

        $this->assertSame(3, $value);
    }

    public function test_cross_join_sequences()
    {
        $assert = function ($entries) {
            $assertions = [
                ['first_name' => 'Thomas', 'last_name' => 'Anderson'],
                ['first_name' => 'Thomas', 'last_name' => 'Smith'],
                ['first_name' => 'Agent', 'last_name' => 'Anderson'],
                ['first_name' => 'Agent', 'last_name' => 'Smith'],
            ];

            foreach ($assertions as $key => $assertion) {
                foreach ($assertions as $key => $assertion) {
                    $this->assertSame(
                        $assertion,
                        $entries[$key]->data()->only('first_name', 'last_name')->all(),
                    );
                }
            }
        };

        $entriesByClass = FactoryTestEntryFactory::times(4)
            ->state(
                new CrossJoinSequence(
                    [['first_name' => 'Thomas'], ['first_name' => 'Agent']],
                    [['last_name' => 'Anderson'], ['last_name' => 'Smith']],
                ),
            )
            ->make();

        $assert($entriesByClass);

        $entriesByMethod = FactoryTestEntryFactory::times(4)
            ->crossJoinSequence(
                [['first_name' => 'Thomas'], ['first_name' => 'Agent']],
                [['last_name' => 'Anderson'], ['last_name' => 'Smith']],
            )
            ->make();

        $assert($entriesByMethod);
    }

    public function test_can_be_macroable()
    {
        $factory = FactoryTestEntryFactory::new();

        $factory->macro('getFoo', function () {
            return 'Hello World';
        });

        $this->assertSame('Hello World', $factory->getFoo());
    }

    public function test_factory_can_conditionally_execute_code()
    {
        FactoryTestEntryFactory::new()
            ->when(true, function () {
                $this->assertTrue(true);
            })
            ->when(false, function () {
                $this->fail('Unreachable code that has somehow been reached.');
            })
            ->unless(false, function () {
                $this->assertTrue(true);
            })
            ->unless(true, function () {
                $this->fail('Unreachable code that has somehow been reached.');
            });
    }

    public function test_entry_can_be_created_in_site()
    {
        $entry = FactoryTestEntryFactory::new()->site('german')->create();
        $this->assertSame('german', $entry->locale());

        $entry = FactoryTestEntryFactory::new()->site('nonexsiting_site')->create();
        $this->assertSame($entry->sites()->first(), $entry->locale());

        $entries = FactoryTestEntryFactory::times(10)->site('random')->create();
        $entries->each(fn ($entry) => $this->assertContains($entry->locale(), ['default', 'german']));

        $entries = FactoryTestEntryFactory::times(10)->site('sequence')->create();
        $entries->each(fn ($entry, $index) => $this->assertSame($index % 2 === 0 ? 'default' : 'german', $entry->locale()));
    }

    public function test_term_can_be_created_in_site()
    {
        $term = FactoryTestTermFactory::new()->site('german')->create();
        $this->assertNotEmpty($term->dataForLocale('default'));
        $this->assertNotEmpty($term->dataForLocale('german'));

        $term = FactoryTestTermFactory::new()->site('nonexsiting_site')->create();
        $this->assertNotEmpty($term->dataForLocale('default'));
        $this->assertEmpty($term->dataForLocale('nonexsiting_site'));

        $terms = FactoryTestTermFactory::times(10)->site('random')->create();
        $localizations = $terms->map->fileData()->flatMap(fn ($data) => data_get($data, 'localizations', []));
        $this->assertNotContains($localizations, ['random']);

        $terms = FactoryTestTermFactory::times(10)->site('sequence')->create();
        $localizations = $terms->map->fileData()->map(fn ($data) => data_get($data, 'localizations', []));
        $this->assertEquals($localizations->filter()->count(), 5);
    }

    public function test_can_set_publish_state()
    {
        $entry = FactoryTestEntryFactory::new()->published(true)->create();
        $this->assertSame(true, $entry->published());

        $entry = FactoryTestEntryFactory::new()->published(false)->create();
        $this->assertSame(false, $entry->published());

        $entry = FactoryTestEntryFactory::new()->published('false')->create();
        $this->assertSame(false, $entry->published());

        $entry = FactoryTestEntryFactory::new()->site('random')->create();
        $this->assertContains($entry->published(), [true, false]);

        $entry = FactoryTestEntryFactory::new()->site('anything')->create();
        $this->assertSame(true, $entry->published());
    }
}

class FactoryTestEntryFactory extends Factory
{
    protected string $contentModel = 'collections.pages.page';

    public function definition(): array
    {
        return [
            'title' => $this->faker->realText(20),
        ];
    }
}

class FactoryTestTermFactory extends Factory
{
    protected string $contentModel = 'taxonomies.tags.tag';

    public function definition(): array
    {
        return [
            'title' => $this->faker->realText(20),
        ];
    }
}

class FactoryTestPostFactory extends Factory
{
    protected string $contentModel = 'collections.posts.post';

    public function definition(): array
    {
        return [
            // 'user_id' => FactoryTestUserFactory::new(), // TODO: Add support for user factory.
            'title' => $this->faker->realText(20),
            'linked_entry' => FactoryTestEntryFactory::new(),
        ];
    }
}
