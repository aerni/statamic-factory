<?php

namespace Aerni\Factory;

use Faker\Generator as Faker;
use Illuminate\Support\Collection as SupportCollection;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Facades\User;
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
     * The config of the factory.
     *
     * @var array
     */
    protected $config;

    /**
     * The type of content.
     *
     * @var string
     */
    protected $contentType;

    /**
     * The handle of the content.
     *
     * @var string
     */
    protected $contentHandle;

    /**
     * The handle of the blueprint.
     *
     * @var string
     */
    protected $blueprintHandle;

    /**
     * The amount of entries/terms to create.
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
        $this->config = config('factory');
    }

    /**
     * Run the factory.
     *
     * @param string $contentType
     * @param string $contentHandle
     * @param string $blueprintHandle
     * @param int $amount
     * @return void
     */
    public function run(string $contentType, string $contentHandle, string $blueprintHandle, int $amount): void
    {
        $this->contentType = $contentType;
        $this->contentHandle = $contentHandle;
        $this->blueprintHandle = $blueprintHandle;
        $this->amount = $amount;

        $this->content = $this->content();
        $this->fakeableItems = $this->fakeableItems();

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
            return Collection::find($this->contentHandle);
        }

        if ($this->contentType === 'Taxonomy') {
            return Taxonomy::find($this->contentHandle);
        }
    }

    /**
     * Get the fakeable items from the blueprint.
     *
     * @return SupportCollection
     */
    protected function fakeableItems(): SupportCollection
    {
        $blueprintItems = Blueprint::find($this->blueprintHandle)->fields()->items();

        $filtered = $this->filterItems($blueprintItems);
        $mapped = $this->mapItems($filtered);

        return $mapped;
    }

    /**
     * Only return items that include a "faker" key.
     *
     * @param SupportCollection $items
     * @return SupportCollection
     */
    protected function filterItems(SupportCollection $items): SupportCollection
    {
        return $items->filter(function ($value) {
            return collect($value['field'])->has('faker');
        });
    }

    /**
     * Map the items.
     *
     * @param SupportCollection $items
     * @return SupportCollection
     */
    protected function mapItems(SupportCollection $items): SupportCollection
    {
        return $items->flatMap(function ($item) {
            return [
                $item['handle'] => $item['field']['faker'],
            ];
        });
    }

    /**
     * Make content based on its type.
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
     * Create $amount of entries with fake data.
     *
     * @param int $amount
     * @return void
     */
    protected function makeEntry(int $amount): void
    {
        for ($i = 0; $i < $amount; $i++) {
            $fakeData = $this->fakeData()->merge([
                'title' => $this->title(),
            ]);

            Entry::make()
                ->collection($this->contentHandle)
                ->blueprint($this->blueprintHandle)
                ->locale(key(config('statamic.sites.sites')))
                ->published($this->config['published'])
                ->slug(Str::slug($fakeData['title']))
                ->data($fakeData)
                ->set('updated_by', User::all()->random()->id())
                ->set('updated_at', now()->timestamp)
                ->save();
        }
    }

    /**
     * Create $amount of terms with fake data.
     *
     * @param int $amount
     * @return void
     */
    protected function makeTerm(int $amount): void
    {
        for ($i = 0; $i < $amount; $i++) {
            $fakeData = $this->fakeData()->merge([
                'title' => $this->title(),
            ]);

            Term::make()
                ->taxonomy($this->contentHandle)
                ->slug(Str::slug($fakeData['title']))
                ->data($fakeData)
                ->save();
        }
    }

    /**
     * Create fake data for the fakeable items.
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
     * Create fake data of the given type.
     *
     * @param string $type
     * @return string
     */
    protected function fakeItem(string $type): string
    {
        return $this->faker->$type;
    }

    /**
     * Create a fake title.
     *
     * @return string
     */
    protected function title(): string
    {
        $lorem = $this->config['title']['lorem'];
        $minChars = $this->config['title']['chars'][0];
        $maxChars = $this->config['title']['chars'][1];

        if ($lorem) {
            $title = $this->faker->text($this->faker->numberBetween($minChars, $maxChars));
            return Str::removeRight($title, '.');
        }

        $title = $this->faker->realText($this->faker->numberBetween($minChars, $maxChars));
        return Str::removeRight($title, '.');
    }
}
