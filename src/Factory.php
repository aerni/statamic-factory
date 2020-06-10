<?php

namespace Aerni\Factory;

use Faker\Generator as Faker;
use Illuminate\Support\Collection as SupportCollection;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Support\Str;

class Factory
{
    /**
     * The faker instance.
     *
     * @var Faker
     */
    protected $faker;

    /**
     * The type of content
     *
     * @var string
     */
    protected $contentType;

    /**
     * The handle of the content
     *
     * @var string
     */
    protected $handle;

    /**
     * The amount of entries/terms to create
     *
     * @var int
     */
    protected $amount;

    /**
     * The content.
     *
     * @var SupportCollection
     */
    protected $content;

    /**
     * The fakeable items from the blueprints.
     *
     * @var SupportCollection
     */
    protected $fakeableItems;

    /**
     * Create a new factory instance.
     *
     * @return void
     */
    public function __construct(Faker $faker)
    {
        $this->faker = $faker;
    }

    /**
     * Run the factory.
     *
     * @param string $contentType
     * @param string $handle
     * @param int $amount
     * @return void
     */
    public function run(string $contentType, string $handle, int $amount): void
    {
        $this->contentType = $contentType;
        $this->handle = $handle;
        $this->amount = $amount;

        $this->content = $this->content();
        $this->fakeableItems = $this->fakeableItems($this->blueprints());

        $this->makeContent();
    }

    /**
     * Get the content.
     *
     * @return mixed
     */
    protected function content()
    {
        if ($this->contentType === 'Collection') {
            return Collection::find($this->handle);
        }

        if ($this->contentType === 'Taxonomy') {
            return Taxonomy::find($this->handle);
        }
    }

    /**
     * Get the blueprints of the content.
     *
     * @return SupportCollection
     */
    protected function blueprints(): SupportCollection
    {
        if ($this->contentType === 'Collection') {
            return $this->content->entryBlueprints();
        }

        if ($this->contentType === 'Taxonomy') {
            return $this->content->termBlueprints();
        }
    }

    /**
     * Get the fakeable items.
     *
     * @param SupportCollection $blueprints
     * @return SupportCollection
     */
    protected function fakeableItems(SupportCollection $blueprints): SupportCollection
    {
        $items = $blueprints->map(function ($blueprint) {
            return $blueprint->fields()->items();
        });

        $filtered = $items->mapWithKeys(function ($item) {
            return $this->processItems($item);
        });

        return $filtered;
    }

    /**
     * Process Items.
     *
     * @param SupportCollection $items
     * @return SupportCollection
     */
    protected function processItems(SupportCollection $items): SupportCollection
    {
        $filtered = $this->filterFields($items);
        $mapped = $this->mapFieldItems($filtered);
        
        return $mapped;
    }

    /**
     * Only return items that include a "faker" field
     *
     * @param SupportCollection $items
     * @return SupportCollection
     */
    protected function filterFields(SupportCollection $items): SupportCollection
    {
        return $items->filter(function ($value) {
            return collect($value['field'])->has('faker');
        });
    }

    /**
     * Map the field items.
     *
     * @param SupportCollection $items
     * @return SupportCollection
     */
    protected function mapFieldItems(SupportCollection $items): SupportCollection
    {
        return $items->flatMap(function ($item) {
            return [
                $item['handle'] => $item['field']['faker'],
            ];
        });
    }

    /**
     * Create fake data.
     *
     * @return SupportCollection
     */
    protected function fakeData(): SupportCollection
    {
        return $this->fakeableItems->map(function ($item) {
            return $this->fakeItem($item);
        });
    }

    /**
     * Create a fake item of the given type.
     *
     * @param string $type
     * @return string
     */
    protected function fakeItem(string $type): string
    {
        return $this->faker->$type;
    }

    /**
     * Create content.
     *
     * @return void
     */
    protected function makeContent(): void
    {
        if ($this->contentType === 'Collection') {
            $this->makeEntry($this->amount);
        }

        if ($this->contentType === 'Taxonomy') {
            $this->makeTerm($this->amount);
        }
    }

    /**
     * Create an entry with the fake data.
     *
     * @param int $amount
     * @return void
     */
    protected function makeEntry(int $amount): void
    {
        for ($i = 0; $i < $amount; $i++) {
            $fakeData = $this->fakeData()->merge([
                'title' => $this->title(rand(1, 5)),
            ]);

            Entry::make()
                ->collection($this->content->handle())
                ->locale('default')
                ->published(true)
                ->slug(Str::slug($fakeData['title']))
                ->data($fakeData)
                ->save();
        }
    }

    /**
     * Create a term with the fake data.
     *
     * @param int $amount
     * @return mixed
     */
    protected function makeTerm(int $amount)
    {
        for ($i = 0; $i < $amount; $i++) {
            $fakeData = $this->fakeData()->merge([
                'title' => $this->title(rand(1, 5)),
            ]);

            Term::make()
                ->taxonomy($this->content->handle())
                ->slug(Str::slug($fakeData['title']))
                ->data($fakeData)
                ->save();
        }
    }

    /**
     * Create a title using faker.
     *
     * @param int $wordCount
     * @return string
     */
    protected function title(int $wordCount): string
    {
        $sentence = $this->faker->sentence($wordCount);

        return substr($sentence, 0, strlen($sentence) - 1);
    }
}
