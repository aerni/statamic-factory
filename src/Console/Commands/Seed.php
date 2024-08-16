<?php

namespace Aerni\Factory\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Taxonomy;

use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class Seed extends Command
{
    use RunsInPlease;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statamic:seed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed Statamic with entries and terms';

    protected Collection $entryFactories;

    protected Collection $termFactories;

    protected Collection $collections;

    protected Collection $taxonomies;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $entryFactoryDirectory = base_path('database/factories/statamic/collections');
        $termFactoryDirectory = base_path('database/factories/statamic/taxonomies');

        $this->entryFactories = File::isDirectory($entryFactoryDirectory)
            ? collect(File::allFiles($entryFactoryDirectory))
            : collect();

        $this->termFactories = File::isDirectory($termFactoryDirectory)
            ? collect(File::allFiles($termFactoryDirectory))
            : collect();

        $this->collections = CollectionFacade::all()->filter(function ($collection) {
            return $this->entryFactories->map->getRelativePath()->contains($collection->handle());
        });

        $this->taxonomies = Taxonomy::all()->filter(function ($taxonomy) {
            return $this->termFactories->map->getRelativePath()->contains($taxonomy->handle());
        });

        $factoryType = select(
            label: 'Which type of content do you want to create?',
            options: [
                'entry' => 'Entry',
                'term' => 'Term',
            ],
            validate: function (string $value) {
                return match ($value) {
                    'entry' => $this->collections->isEmpty()
                        ? 'You need to create at least one entry factory.'
                        : null,
                    'term' => $this->taxonomies->isEmpty()
                        ? 'You need to create at least one term factory.'
                        : null,
                };
            },
        );

        $factory = $this->getFactory($factoryType);

        $seeder = $this->getSeederFromFactory($factory);

        class_exists($seeder)
            ? app($seeder)->__invoke()
            : $factory::new()->count($this->askForAmount())->create();

        info('The content was successfully created!');
    }

    protected function getFactory(string $factoryType): string
    {
        $models = match ($factoryType) {
            'entry' => $this->collections,
            'term' => $this->taxonomies,
        };

        $repository = match ($factoryType) {
            'entry' => 'collections',
            'term' => 'taxonomies',
        };

        $selectedModel = select(
            label: 'For which '.Str::singular($repository).' do you want to create content?',
            options: $models->mapWithKeys(fn ($model) => [$model->handle() => $model->title()]),
        );

        $factories = $this->entryFactories
            ->filter(fn ($factory) => $factory->getRelativePath() === $selectedModel)
            ->values();

        $selectedFactory = select(
            label: "Which {$selectedModel} factory do you want to use?",
            options: $factories->map(fn ($factory) => $this->generateFactoryNameFromPath($factory->getFilename())),
        );

        $factory = $factories->firstWhere(fn ($factory) => $this->generateFactoryNameFromPath($factory->getFilename()) === $selectedFactory);

        return Str::remove('.php', $this->generateNamespaceFromPath($factory->getPathname()));
    }

    protected function getSeederFromFactory(string $factory): string
    {
        return Str::of($factory)
            ->replace('Factories', 'Seeders')
            ->replace('Factory', 'Seeder');
    }

    protected function generatePathFromNamespace(string $namespace): string
    {
        $name = str($namespace)->finish('\\')->replaceFirst(app()->getNamespace(), '')->lower();

        return base_path(str_replace('\\', '/', $name));
    }

    protected function generateNamespaceFromPath(string $path): string
    {
        return collect(explode('/', $this->getRelativePath($path)))
            ->map(ucfirst(...))
            ->implode('\\');
    }

    protected function generateFactoryNameFromPath(string $path): string
    {
        return Str::of($path)
            ->afterLast('/')
            ->remove('Factory.php');
    }

    protected function getRelativePath(string $path): string
    {
        return str_replace(base_path().'/', '', $path);
    }

    protected function askForAmount(): int
    {
        return text(
            label: 'How many entries do you want to create?',
            default: 1,
            validate: fn (string $value) => match (true) {
                ! is_numeric($value) => 'The value must be a number.',
                $value < 1 => 'The value must be at least 1.',
                default => null
            }
        );
    }
}
