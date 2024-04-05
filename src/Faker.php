<?php

namespace Aerni\Factory;

use Faker\Generator;
use Aerni\Factory\Mapper;
use Statamic\Support\Str;
use Statamic\Fields\Blueprint;
use Stillat\Primitives\Parser;
use Illuminate\Support\Collection;
use Stillat\Primitives\MethodRunner;

class Faker
{
    public function __construct(
        protected Blueprint $blueprint,
        protected Generator $generator,
        protected Mapper $mapper,
        protected Parser $parser,
        protected MethodRunner $runner
    )
    {
        //
    }

    /**
     * Create fake data for the fakeable items.
     */
    public function data(): array
    {
        return Utils::mapRecursive($this->fakeableItems(), function ($value, $key) {
            if ($this->isFakerFormatter($value, $key)) {
                return $this->fakeItem($value);
            }

            return $value;
        });
    }

    /**
     * Create a fake title.
     */
    public function title(): string
    {
        $minChars = config('factory.title.chars.0');
        $maxChars =  config('factory.title.chars.1');

        $title = config('factory.title.real_text')
            ? $this->generator->realText($this->generator->numberBetween($minChars, $maxChars))
            : $this->generator->text($this->generator->numberBetween($minChars, $maxChars));

        return Str::removeRight($title, '.');
    }

    /**
     * Get the fakeable items from the blueprint.
     */
    protected function fakeableItems(): array
    {
        $blueprintItems = $this->blueprint->fields()->items();
        $filtered = $this->filterItems($blueprintItems);
        $mapped = $this->mapper->mapItems($filtered);

        return $mapped;
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
     * Create fake data with the given Faker formatter.
     *
     * @return mixed
     */
    protected function fakeItem(string $fakerFormatter)
    {
        return $this->runner->run(
            $this->parser->parseMethods($fakerFormatter),
            $this->generator
        );
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
