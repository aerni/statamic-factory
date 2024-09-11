<?php

namespace Aerni\Factory\Factories;

use Closure;
use Faker\Generator;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Factories\CrossJoinSequence;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Statamic\Contracts\Auth\User;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Facades\Site;

abstract class Factory
{
    use Conditionable, Macroable {
        __call as macroCall;
    }

    public static string $namespace = 'Database\\Factories\\Statamic\\';

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

    abstract public function newModel(array $attributes = []);

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

    public function makeOne($attributes = []): Entry|Term|User
    {
        return $this->count(null)->make($attributes);
    }

    public function make(array $attributes = []): Collection|Entry|Term|User
    {
        if (! empty($attributes)) {
            return $this->state($attributes)->make([]);
        }

        if ($this->count === null) {
            return tap($this->state($attributes)->makeInstance(), function ($instance) {
                $this->callAfterMaking(collect([$instance]));
            });
        }

        if ($this->count < 1) {
            return collect();
        }

        $instances = collect(array_map(function () use ($attributes) {
            return $this->state($attributes)->makeInstance();
        }, range(1, $this->count)));

        $this->callAfterMaking($instances);

        return $instances;
    }

    public function createOne($attributes = []): Entry|Term|User
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

    // TODO: Add createManyQuietly()

    // TODO: Add createQuietly()

    public function create(array $attributes = []): Collection|Entry|Term|User
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

    protected function makeInstance(): Entry|Term|User
    {
        return $this->newModel($this->getExpandedAttributes());
    }

    protected function getExpandedAttributes(): array
    {
        return $this->expandAttributes($this->getRawAttributes());
    }

    protected function getRawAttributes(): array
    {
        return $this->states
            ->when(
                method_exists($this, 'evaluateSiteStates'),
                fn ($states) => $this->evaluateSiteStates($states)
            )
            ->reduce(function (array $carry, $state) {
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
                    // TODO: Also support users
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
                    // TODO: Also support users
                    Collection::wrap(($model instanceof Entry || $model instanceof Term) ? func_get_args() : $model)
                        ->flatten()
                )
                ->groupBy($this->getModelNameFromClass(...)),
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

    protected function withFaker(): Generator
    {
        $site = method_exists($this, 'getSitesFromContentModel')
            ? Site::get($this->getSitesFromContentModel()->first())
            : Site::default();

        return Container::getInstance()->makeWith(Generator::class, ['locale' => $site->locale()]);
    }

    public function modelName(): string
    {
        return str($this->model)->afterLast('\\');
    }

    protected function getModelNameFromClass($class): string
    {
        return match (true) {
            $class instanceof Entry => 'Entry'.'\\'.ucfirst($class->collectionHandle()).'\\'.ucfirst($class->blueprint()),
            $class instanceof Term => 'Term'.'\\'.ucfirst($class->taxonomyHandle()).'\\'.ucfirst($class->blueprint()),
            $class instanceof User => 'User',
            $class instanceof self => $class->modelName(),
        };
    }
}
