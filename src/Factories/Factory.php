<?php

namespace Aerni\Factory\Factories;

use Closure;
use Exception;
use Faker\Generator;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Factories\CrossJoinSequence;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Facades\Site;

abstract class Factory
{
    use Conditionable, DefinitionHelpers, Macroable {
        __call as macroCall;
    }

    public static string $namespace = 'Database\\Factories\\Statamic\\';

    protected string $contentModel;

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
        return $this->newContentModel($this->getExpandedAttributes());
    }

    protected function getExpandedAttributes(): array
    {
        return $this->expandAttributes($this->getRawAttributes());
    }

    protected function getRawAttributes(): array
    {
        $definition = $this->states->reduce(function (array $carry, $state) {
            if ($state instanceof Closure) {
                $state = $state->bindTo($this);
            }

            return array_merge($carry, $state($carry));
        }, $this->definition());

        $this->faker = $this->withFaker(Arr::get($definition, 'site'));

        return array_merge($definition, $this->definition());
    }

    protected function expandAttributes(array $definition)
    {
        return collect($definition)
            ->map($evaluateRelations = function ($attribute) {
                if ($attribute instanceof self) {
                    $attribute = $this->getRandomRecycledModel($attribute->contentModelName())?->id()
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

    public function site(string $site): self
    {
        return match ($site) {
            'random' => $this->sequence(fn (Sequence $sequence) => ['site' => $this->getSitesFromContentModel()->random()]),
            default => $this->set('site', $site),
        };
    }

    public function published(bool|string $published): self
    {
        return match ($published) {
            'random' => $this->sequence(fn (Sequence $sequence) => ['published' => collect([true, false])->random()]),
            false, 'false' => $this->set('published', false),
            default => $this->set('published', true),
        };
    }

    protected function getSitesFromContentModel(): Collection
    {
        $contentModel = $this->newContentModel();

        return match (true) {
            $contentModel instanceof Entry => $contentModel->sites(),
            $contentModel instanceof Term => $contentModel->taxonomy()->sites(),
        };
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
                ->groupBy($this->getContentModelNameFromContentClass(...)),
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

    public function newContentModel(array $attributes = []): Entry|Term
    {
        return match (true) {
            $this->contentModelType() === 'collections' => $this->newEntry($attributes),
            $this->contentModelType() === 'taxonomies' => $this->newTerm($attributes),
            default => throw new \Exception("The repository \"{$this->contentModelType()}\" is not supported."),
        };
    }

    protected function newEntry(array $attributes = []): Entry
    {
        $entry = \Statamic\Facades\Entry::make()
            ->collection($this->contentModelHandle())
            ->blueprint($this->contentModelBlueprint());

        if ($slug = Arr::pull($attributes, 'slug')) {
            $entry->slug($slug);
        }

        if (($site = Arr::pull($attributes, 'site')) && $entry->sites()->contains($site)) {
            $entry->locale($site);
        }

        $entry->published(Arr::pull($attributes, 'published'));

        return $entry->data($attributes);
    }

    protected function newTerm(array $attributes = []): Term
    {
        $term = \Statamic\Facades\Term::make()
            ->taxonomy($this->contentModelHandle())
            ->blueprint($this->contentModelBlueprint());

        if ($slug = Arr::pull($attributes, 'slug')) {
            $term->slug($slug);
        }

        if (($site = Arr::pull($attributes, 'site')) && $term->taxonomy()->sites()->contains($site)) {
            $localizedTerm = $term->in($site);
        } else {
            $localizedTerm = $term->inDefaultLocale();
        }

        return $localizedTerm->data($attributes)->term();
    }

    protected function withFaker(?string $site = null): Generator
    {
        return Container::getInstance()->makeWith(Generator::class, ['locale' => $this->getLocaleFromSite($site)]);
    }

    protected function getLocaleFromSite(?string $site = null): string
    {
        return Site::get($site)?->locale() ?? Site::default()->locale();
    }

    protected function contentModelType(): string
    {
        return Str::before($this->contentModelName(), '.');
    }

    protected function contentModelHandle(): string
    {
        return Str::between($this->contentModelName(), '.', '.');
    }

    protected function contentModelBlueprint(): string
    {
        return Str::afterLast($this->contentModelName(), '.');
    }

    public function contentModelName(): string
    {
        $name = $this->contentModel
            ?? str(get_class($this))
                ->remove(static::$namespace)
                ->remove('Factory')
                ->lower()
                ->replace('\\', '.');

        return collect(explode('.', $name))->filter()->count() === 3
            ? $name
            : throw new Exception("The model name \"{$name}\" is incomplete. Make sure it follows this convention: \"{contentModelType}.{contentModelHandle}.{contentModelBlueprint}\".");
    }

    protected function getContentModelNameFromContentClass(Entry|Term $content): string
    {
        return match (true) {
            $content instanceof Entry => "collections.{$content->collectionHandle()}.{$content->blueprint()}",
            $content instanceof Term => "taxonomies.{$content->taxonomyHandle()}.{$content->blueprint()}",
        };
    }
}
