<?php

namespace Aerni\Factory;

use Faker\Generator as Faker;
use Illuminate\Support\Collection;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Facades\Term;
use Statamic\Facades\User;
use Statamic\Support\Str;
use Stillat\Primitives\MethodRunner;
use Stillat\Primitives\Parser;

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
     * The parser instance.
     *
     * @var Parser
     */
    protected $parser;

    /**
     * The method runner instance.
     *
     * @var MethodRunner
     */
    protected $runner;

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
    public function __construct(Faker $faker, Mapper $mapper, Parser $parser, MethodRunner $runner)
    {
        $this->faker = $faker;
        $this->mapper = $mapper;
        $this->parser = $parser;
        $this->runner = $runner;

        $this->config = config('factory');
    }

    /**
     * Run the factory.
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
     */
    protected function fakeableItems(): array
    {
        $blueprintItems = $this->blueprint()->fields()->items();
        $filtered = $this->filterItems($blueprintItems);
        $mapped = $this->mapper->mapItems($filtered);

        return $mapped;
    }

    protected function blueprint(): \Statamic\Fields\Blueprint
    {
        if ($this->contentType === 'entry') {
            return Blueprint::find("collections/{$this->contentHandle}/{$this->blueprintHandle}");
        }

        if ($this->contentType === 'term') {
            return Blueprint::find("taxonomies/{$this->contentHandle}/{$this->blueprintHandle}");
        }

        if ($this->contentType === 'global') {
            return Blueprint::find("globals/{$this->contentHandle}");
        }
    }

    /**
     * Filter the blueprint items.
     */
    protected function filterItems(Collection $items): array
    {
        return $items->map(function ($item) {
            if ($this->isBardOrReplicator($item)) {
                $item['field']['sets'] = $this->sets($item)
                    ->map(function ($set) {
                        $set['fields'] = $this->filterItems($this->fields($set));

                        return $set;
                    })
                    ->filter(function ($set) {
                        return $this->hasFactory($set) && $this->hasFields($set);
                    })->toArray();
            }

            if ($this->isGrid($item)) {
                $item['field']['fields'] = $this->filterItems($this->fields($item));
            }

            return $item;
        })->filter(function ($item) {
            if ($this->isBardOrReplicator($item)) {
                return $this->hasSets($item);
            }

            if ($this->isGrid($item)) {
                return $this->hasFactory($item) && $this->hasFields($item);
            }

            return $this->hasFactory($item);
        })->toArray();
    }

    /**
     * Get the fields or an empty array.
     */
    protected function fields(array $item): Collection
    {
        if (array_key_exists('field', $item)) {
            return collect($item['field']['fields'] ?? []);
        }

        if (array_key_exists('fields', $item)) {
            return collect($item['fields'] ?? []);
        }
    }

    /**
     * Collect the sets from an item.
     */
    protected function sets(array $item): Collection
    {
        return collect($item['field']['sets'] ?? []);
    }

    /**
     * Make content based on its type.
     */
    protected function makeContent(): void
    {
        if ($this->contentType === 'Asset') {
            $this->makeAsset($this->amount);
        }

        if ($this->contentType === 'entry') {
            $this->makeEntry($this->amount);
        }

        if ($this->contentType === 'global') {
            $this->makeGlobal();
        }

        if ($this->contentType === 'term') {
            $this->makeTerm($this->amount);
        }
    }

    /**
     * TODO: Fakers image implementation is unstable. Use another implementation to create fake images.
     * TODO: Make image creation optional and instead focus on creating fake data for existing assets based on the container's blueprint.
     *
     * Create $amount of assets with fake data.
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

            Asset::findById($this->contentHandle.'::'.$image)
                ->data($fakeData)
                ->save();
        }
    }

    /**
     * Create $amount of entries with fake data.
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
                ->locale(Site::default()->handle())
                ->published($this->config['published'])
                ->slug(Str::slug($fakeData['title']))
                ->data($fakeData)
                ->set('updated_by', User::all()->random()->id())
                ->set('updated_at', now()->timestamp)
                ->save();
        }
    }

    /**
     * Fill the global set with fake data.
     */
    protected function makeGlobal(): void
    {
        GlobalSet::findByHandle($this->contentHandle)
            ->inDefaultSite()
            ->data($this->fakeData())
            ->save();
    }

    /**
     * Create $amount of terms with fake data.
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
     */
    protected function fakeData(): array
    {
        return Utils::array_map_recursive($this->fakeableItems(), function ($value, $key) {
            if ($this->isFakerFormatter($value, $key)) {
                return $this->fakeItem($value);
            }

            return $value;
        });
    }

    /**
     * Create fake data with the given Faker formatter.
     *
     * @return mixed
     */
    protected function fakeItem(string $fakerFormatter)
    {
        return $this->runner->run(
            $this->parser->parseMethods($fakerFormatter),
            $this->faker
        );
    }

    /**
     * Create a fake title.
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

    /**
     * Check if an item is of fieldtype bard or replicator.
     */
    protected function isBardOrReplicator(array $item): bool
    {
        if ($item['field']['type'] === 'bard') {
            return true;
        }

        if ($item['field']['type'] === 'replicator') {
            return true;
        }

        return false;
    }

    /**
     * Check if an item is of fieldtype grid.
     */
    protected function isGrid(array $item): bool
    {
        if ($item['field']['type'] === 'grid') {
            return true;
        }

        return false;
    }

    /**
     * Check if an item has factory key.
     */
    protected function hasFactory(array $item): bool
    {
        if (array_key_exists('field', $item)) {
            return collect($item['field'])->has('factory');
        }

        if (array_key_exists('factory', $item)) {
            return collect($item)->has('factory');
        }

        return false;
    }

    /**
     * Check if an item has fields.
     */
    protected function hasFields(array $item): bool
    {
        if (array_key_exists('field', $item)) {
            return collect($item['field']['fields'])->isNotEmpty();
        }

        if (array_key_exists('fields', $item)) {
            return collect($item['fields'])->isNotEmpty();
        }

        return false;
    }

    /**
     * Check if an item has sets.
     */
    protected function hasSets(array $item): bool
    {
        if (collect($item['field']['sets'])->isEmpty()) {
            return false;
        }

        return true;
    }

    /**
     * Check if the passed value is a faker formatter.
     *
     * @param  mixed  $value
     */
    protected function isFakerFormatter($value, string $key): bool
    {
        if (is_array($value)) {
            return false;
        }

        if ($key === 'type') {
            return false;
        }

        if ($key === 'enabled') {
            return false;
        }

        return true;
    }
}
