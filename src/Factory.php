<?php

namespace Aerni\Factory;

use Faker\Generator as Faker;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Str;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Term;
use Statamic\Facades\User;

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

        $this->makeContent();
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
        if ($this->contentType === 'Asset') {
            $this->makeAsset($this->amount);
        }

        if ($this->contentType === 'Collection') {
            $this->makeEntry($this->amount);
        }

        if ($this->contentType === 'Global') {
            $this->makeGlobal();
        }

        if ($this->contentType === 'Taxonomy') {
            $this->makeTerm($this->amount);
        }
    }

    /**
     * TODO: Fakers image implementation is unstable. Use another implementation to create fake images.
     * TODO: Make image creation optional and instead focus on creating fake data for existing assets based on the container's blueprint.
     *
     * Create $amount of assets with fake data.
     *
     * @param int $amount
     * @return void
     */
    protected function makeAsset(int $amount): void
    {
        $diskPath = AssetContainer::find($this->contentHandle)->diskPath();

        for ($i = 0; $i < $amount; $i++) {
            $fakeData = $this->fakeData();

            $image = $this->faker->image(
                $diskPath,
                $this->config['assets']['width'],
                $this->config['assets']['height'],
                $this->config['assets']['category'],
                false
            );

            Asset::findById($this->contentHandle . '::' . $image)
                ->data($fakeData)
                ->save();
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
            $fakeData = collect([
                'title' => $this->title(),
            ])->merge($this->fakeData());

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
     * TODO: Figure out how to save data to a Global Set.
     *
     * Fill the global set with fake data.
     *
     * @return void
     */
    protected function makeGlobal(): void
    {
        $fakeData = $this->fakeData();
        
        dd(GlobalSet::find($this->contentHandle)->fileData());
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
            $fakeData = collect([
                'title' => $this->title(),
            ])->merge($this->fakeData());

            Term::make()
                ->taxonomy($this->contentHandle)
                ->blueprint($this->blueprintHandle)
                ->slug(Str::slug($fakeData['title']))
                ->data($fakeData)
                ->set('updated_by', User::all()->random()->id())
                ->set('updated_at', now()->timestamp)
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
        return $this->fakeableItems()->map(function ($item) {
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
        if (Str::contains($type, '(')) {
            $function = Str::of($type)->before('(')->__toString();
            $arguments = Str::between($type, '(', ')');
            
            return $this->faker->$function($arguments);
        }
        
        return $this->faker->$type();
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
            return $this->faker->text($this->faker->numberBetween($minChars, $maxChars));
        }

        return $this->faker->realText($this->faker->numberBetween($minChars, $maxChars));
    }
}
