<?php

namespace Aerni\Factory;

use Aerni\Factory\Mapper;
use Faker\Generator as Faker;
use Illuminate\Support\Collection as SupportCollection;
use Statamic\Support\Str;
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
     * The mapper instance.
     *
     * @var Mapper
     */
    protected $mapper;

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
    public function __construct(Faker $faker, Mapper $mapper)
    {
        $this->faker = $faker;
        $this->mapper = $mapper;

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
     * Get the items of the blueprint.
     *
     * @return SupportCollection
     */
    protected function blueprintItems(): SupportCollection
    {
        return Blueprint::find($this->blueprintHandle)->fields()->items();
    }

    /**
     * Get the fakeable items from the blueprint.
     *
     * @return array
     */
    protected function fakeableItems(): array
    {
        $filtered = $this->filterItems($this->blueprintItems());
        $mapped = $this->mapper->mapItems($filtered);

        return $mapped->toArray();
    }

    /**
     * Only return items that include a "faker" key.
     *
     * @param SupportCollection $items
     * @return SupportCollection
     */
    protected function filterItemsOld(SupportCollection $items): SupportCollection
    {
        return $items->filter(function ($value) {
            return collect($value['field'])->has('factory');
        });
    }

    /**
     * Filter the fields by supported fieldtypes.
     *
     * @param SupportCollection $fields
     * @return SupportCollection
     */
    private function filterItems(SupportCollection $fields): SupportCollection
    {
        return $fields
            ->map(function ($item) {
                switch ($item['field']['type']) {
                    case 'grid':
                        $item['field']['fields'] = $this->filterItems(collect($item['field']['fields'] ?? []));
                        break;
                }

                return $item;
            })
            ->filter(function ($item) {
                switch ($item['field']['type']) {
                    case 'grid':
                        return count($item['field']['fields'] ?? []) > 0;
                        break;
                    default:
                        break;
                }

                return collect($item['field'])->has('factory');
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
     * @return array
     */
    protected function fakeData(): array
    {
        return Utils::array_map_recursive($this->fakeableItems(), function ($fakerFormatter) {
            return $this->fakeItem($fakerFormatter);
        });
    }

    /**
     * Create fake data with the given Faker formatter.
     *
     * @param string $fakerFormatter
     * @return mixed
     */
    protected function fakeItem(string $fakerFormatter)
    {
        /**
         * This handles Faker formatters with arguments.
         */
        if (Str::containsAll($fakerFormatter, ['(', ')'])) {
            $method = Str::of($fakerFormatter)->before('(')->__toString();
            $arguments = Str::between($fakerFormatter, '(', ')');

            // Create an array of arguments and trim each value.
            $argumentsArray = array_map('trim', explode(',', $arguments));

            // Transform each array value to its correct type.
            $argumentsArray = array_map(function ($item) {
                if (is_numeric($item)) {
                    return (int) $item;
                }

                if ($item === 'true') {
                    return (bool) true;
                }

                if ($item === 'false') {
                    return (bool) false;
                }

                return (string) $item;
            }, $argumentsArray);

            // Pass each array value as argument to the Faker formatter.
            return call_user_func_array([$this->faker, $method], $argumentsArray);
        }
        
        /**
         * This handles simple Faker formatters.
         */
        return $this->faker->$fakerFormatter();
    }

    /**
     * Create a fake title.
     *
     * @return string
     */
    protected function title(): string
    {
        $realText = $this->config['title']['real_text'];
        $minChars = $this->config['title']['chars'][0];
        $maxChars = $this->config['title']['chars'][1];

        if ($realText) {
            $title = $this->faker->realText($this->faker->numberBetween($minChars, $maxChars));
            return Str::removeRight($title, '.');
        }
        
        $title = $this->faker->text($this->faker->numberBetween($minChars, $maxChars));
        return Str::removeRight($title, '.');
    }
}
