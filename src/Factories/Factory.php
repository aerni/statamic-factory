<?php

namespace Aerni\Factory\Factories;

use Closure;
use Faker\Generator;
use Illuminate\Container\Container;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term;

abstract class Factory
{
    use DefinitionHelpers;

    public static string $namespace = 'Database\\Factories\\Statamic\\';

    protected string $model;

    protected Generator $faker;

    public function __construct(
        protected ?int $count = null,
        protected ?Collection $states = null,
        protected ?Collection $afterMaking = null,
        protected ?Collection $afterCreating = null,
        protected ?Collection $recycle = null,
    ) {
        $this->states ??= new Collection;
        $this->afterMaking ??= new Collection;
        $this->afterCreating ??= new Collection;
        $this->recycle ??= new Collection;
        $this->faker = $this->withFaker();
    }

    abstract public function definition(): array;

    public static function new(array $attributes = []): self
    {
        return (new static)->state($attributes)->configure();
    }

    public static function times(int $count): self
    {
        return static::new()->count($count);
    }

    public function configure(): self
    {
        return $this;
    }

    public function raw($attributes = []): array
    {
        if ($this->count === null) {
            return $this->state($attributes)->getExpandedAttributes();
        }

        return array_map(function () use ($attributes) {
            return $this->state($attributes)->getExpandedAttributes();
        }, range(1, $this->count));
    }

    public function makeOne($attributes = []): Entry|Term
    {
        return $this->count(null)->make($attributes);
    }

    public function make(array $attributes = []): Collection|Entry|Term
    {
        if (! empty($attributes)) {
            return $this->state($attributes)->make([]);
        }

        if ($this->count === null) {
            return tap($this->makeInstance(), function ($instance) {
                $this->callAfterMaking(collect([$instance]));
            });
        }

        if ($this->count < 1) {
            return collect();
        }

        return collect()
            ->range(1, $this->count)
            ->map(fn () => $this->makeInstance())
            ->tap(fn ($instances) => $this->callAfterMaking($instances));
    }

    public function createOne($attributes = []): Entry|Term
    {
        return $this->count(null)->create($attributes);
    }

    public function createMany(int|iterable|null $records = null)
    {
        $records ??= ($this->count ?? 1);

        $this->count = null;

        if (is_numeric($records)) {
            $records = array_fill(0, $records, []);
        }

        return collect($records)->map(fn ($record) => $this->state($record)->create());
    }

    // TODO: Add createOneQuietly()

    // TODO: Add createMany()

    // TODO: Add createManyQuietly()

    // TODO: Add createQuietly()

    public function create(array $attributes = []): Collection|Entry|Term
    {
        if (! empty($attributes)) {
            return $this->state($attributes)->create();
        }

        $instances = $this->make($attributes);

        if ($instances instanceof Collection) {
            return $instances
                ->each(fn ($instance) => $instance->save())
                ->tap(fn ($instances) => $this->callAfterCreating($instances));
        }

        $instances->save();

        $this->callAfterCreating(collect([$instances]));

        return $instances;
    }

    public function lazy(array $attributes = [])
    {
        return fn () => $this->create($attributes);
    }

    protected function makeInstance(): Entry|Term
    {
        return $this->newModel($this->getExpandedAttributes());
    }

    protected function getExpandedAttributes(): array
    {
        return $this->expandAttributes($this->getRawAttributes());
    }

    protected function getRawAttributes(): array
    {
        return $this->states->reduce(function (array $carry, $state) {
            // TODO: Not sure if closures would work as an Entry can't be serialized?
            // There's a failing test for this.
            if ($state instanceof Closure) {
                $state = $state->bindTo($this);
            }

            return array_merge($carry, $state($carry));
        }, $this->definition());
    }

    protected function expandAttributes(array $definition)
    {
        return collect($definition)
            ->map($evaluateRelations = function ($attribute) {
                if ($attribute instanceof self) {
                    $attribute = $this->getRandomRecycledModel($attribute->modelName())?->id()
                        ?? $attribute->recycle($this->recycle)->create()->id();
                } elseif ($attribute instanceof Entry || $attribute instanceof Term) {
                    $attribute = $attribute->id();
                }

                return $attribute;
            })
            ->map(function ($attribute, $key) use (&$definition, $evaluateRelations) {
                if (is_callable($attribute) && ! is_string($attribute) && ! is_array($attribute)) {
                    $attribute = $attribute($definition);
                }

                $attribute = $evaluateRelations($attribute);

                $definition[$key] = $attribute;

                return $attribute;
            })
            ->all();
    }

    public function state(mixed $state): self
    {
        return $this->newInstance([
            'states' => $this->states->concat([
                is_callable($state) ? $state : fn () => $state,
            ]),
        ]);
    }

    public function set($key, $value)
    {
        return $this->state([$key => $value]);
    }

    public function sequence(...$sequence)
    {
        return $this->state(new Sequence(...$sequence));
    }

    public function forEachSequence(...$sequence)
    {
        return $this->state(new Sequence(...$sequence))->count(count($sequence));
    }

    public function crossJoinSequence(...$sequence)
    {
        return $this->state(new CrossJoinSequence(...$sequence));
    }

    public function count(?int $count): self
    {
        return $this->newInstance(['count' => $count]);
    }

    public function recycle($model)
    {
        return $this->newInstance([
            'recycle' => $this->recycle
                ->flatten()
                ->merge(
                    Collection::wrap(($model instanceof Entry || $model instanceof Term) ? func_get_args() : $model)
                        ->flatten()
                )
                ->groupBy($this->getModelNameFromStatamicClass(...)),
        ]);
    }

    public function getRandomRecycledModel($modelName)
    {
        return $this->recycle->get($modelName)?->random();
    }

    public function afterMaking(Closure $callback): self
    {
        return $this->newInstance([
            'afterMaking' => $this->afterMaking->concat([$callback]),
        ]);
    }

    public function afterCreating(Closure $callback): self
    {
        return $this->newInstance([
            'afterCreating' => $this->afterCreating->concat([$callback]),
        ]);
    }

    protected function callAfterMaking(Collection $instances): void
    {
        $instances->each(function ($model) {
            $this->afterMaking->each(fn ($callback) => $callback($model));
        });
    }

    protected function callAfterCreating(Collection $instances): void
    {
        $instances->each(function ($model) {
            $this->afterCreating->each(fn ($callback) => $callback($model));
        });
    }

    protected function newInstance(array $arguments = []): self
    {
        return new static(...array_values(array_merge([
            'count' => $this->count,
            'states' => $this->states,
            'afterMaking' => $this->afterMaking,
            'afterCreating' => $this->afterCreating,
            'recycle' => $this->recycle,
        ], $arguments)));
    }

    public function newModel(array $attributes = []): Entry|Term
    {
        return match (true) {
            $this->modelRepository() === 'collections' => $this->newEntry($attributes),
            $this->modelRepository() === 'taxonomies' => $this->newTerm($attributes),
            default => throw new \Exception("The repository \"{$this->modelRepository()}\" is not supported."),
        };
    }

    protected function newEntry(array $attributes = []): Entry
    {
        $entry = \Statamic\Facades\Entry::make()
            ->collection($this->modelType())
            ->blueprint($this->modelBlueprint());

        if ($slug = Arr::pull($attributes, 'slug')) {
            $entry->slug($slug);
        }

        if ($locale = Arr::pull($attributes, 'locale')) {
            $entry->locale($locale);
        }

        if ($published = Arr::pull($attributes, 'published')) {
            $entry->published($published);
        }

        return $entry->data($attributes);
    }

    protected function newTerm(array $attributes = []): Term
    {
        $term = \Statamic\Facades\Term::make()
            ->taxonomy($this->modelType())
            ->blueprint($this->modelBlueprint());

        if ($slug = Arr::pull($attributes, 'slug')) {
            $term->slug($slug);
        }

        if ($locale = Arr::pull($attributes, 'locale')) {
            return $term->in($locale)->data($attributes);
        }

        return $term->inDefaultLocale()->data($attributes);
    }

    protected function withFaker()
    {
        return Container::getInstance()->make(Generator::class);
    }

    protected function modelRepository(): string
    {
        return $this->getModelDefinitionFromNamespace()['modelRepository'];
    }

    protected function modelType(): string
    {
        return $this->getModelDefinitionFromNamespace()['modelType'];
    }

    protected function modelBlueprint(): ?string
    {
        return $this->getModelDefinitionFromNamespace()['modelBlueprint'];
    }

    protected function getModelDefinitionFromNamespace(): array
    {
        if (isset($this->model)) {
            $factoryNameParts = str($this->model)->lower()->explode('.');
        } else {
            $factoryNameParts = Str::of(get_class($this))
                ->remove(static::$namespace)
                ->remove('Factory')
                ->lower()
                ->explode('\\');
        }

        return [
            'modelRepository' => $factoryNameParts[0],
            'modelType' => $factoryNameParts[1],
            'modelBlueprint' => $factoryNameParts[2] ?? null,
        ];
    }

    public function getModelNameFromStatamicClass(Entry|Term $model): string
    {
        return match (true) {
            $model instanceof Entry => "collections.{$model->collectionHandle()}.{$model->blueprint()}",
            $model instanceof Term => "collections.{$model->taxonomyHandle()}.{$model->blueprint()}",
        };
    }

    public function modelName()
    {
        return implode('.', $this->getModelDefinitionFromNamespace());

        // TODO: Make this work the way Laravel does it.

        // $resolver = static::$modelNameResolver ?? function (self $factory) {
        //     $namespacedFactoryBasename = Str::replaceLast(
        //         'Factory', '', Str::replaceFirst(static::$namespace, '', get_class($factory))
        //     );

        //     $factoryBasename = Str::replaceLast('Factory', '', class_basename($factory));

        //     $appNamespace = static::appNamespace();

        //     return class_exists($appNamespace.'Models\\'.$namespacedFactoryBasename)
        //                 ? $appNamespace.'Models\\'.$namespacedFactoryBasename
        //                 : $appNamespace.$factoryBasename;
        // };

        // return $this->model ?? $resolver($this);
    }
}
