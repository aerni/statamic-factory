<?php

namespace Aerni\Factory\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\Collection;
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

    protected $entryFactories;

    protected $termFactories;

    protected $collections;

    protected $taxonomies;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $entryFactoryDirectory = base_path('database/factories/statamic/collections');
        $termFactoryDirectory = base_path('database/factories/statamic/taxonomies');

        $this->entryFactories = File::isDirectory($entryFactoryDirectory) ? collect(File::allFiles($entryFactoryDirectory)) : collect();
        $this->termFactories = File::isDirectory($termFactoryDirectory) ? collect(File::allFiles($termFactoryDirectory)) : collect();

        // Filter the selection to only show collection/taxonomies that have a factory
        $this->collections = Collection::all()->filter(function ($collection) {
            return $this->entryFactories->map->getRelativePath()->contains($collection->handle());
        });

        // Filter the selection to only show collection/taxonomies that have a factory
        $this->taxonomies = Taxonomy::all()->filter(function ($taxonomy) {
            return $this->termFactories->map->getRelativePath()->contains($taxonomy->handle());
        });

        $type = select(
            label: 'Select the type of content you want to create.',
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

        $factory = $this->getFactory($type);

        $seeder = $this->getSeederFromFactory($factory);

        // If the selected factory has a seeder, use it.
        if (class_exists($seeder)) {
            app($seeder)->__invoke();
            info('The seeder has been run!');
        } else {
            // If the selected factory has no seeder, use a generic seeder and ask how many entries to create.
            $amount = $this->selectAmount('How many entries do you want to create?');
            $factory::new()->count($amount)->create();
            info('The content was successfully created!');
        }
    }

    protected function getFactory(string $type): string
    {
        $models = match ($type) {
            'entry' => $this->collections,
            'term' => $this->taxonomies,
        };

        $selectedModel = select(
            label: 'Select the model of the factory.',
            options: $models->mapWithKeys(fn ($model) => [$model->handle() => $model->title()]),
        );

        $model = $models->firstWhere('handle', $selectedModel);

        $factories = $this->entryFactories->filter(function ($factory) use ($selectedModel) {
            return $factory->getRelativePath() === $selectedModel;
        });

        $selectedFactory = select(
            label: 'Select the factory',
            options: $factories->map(fn ($factory) => $this->generateFactoryNameFromPath($factory->getFilename())),
        );

        $factory = $factories->firstWhere(fn ($factory) => $this->generateFactoryNameFromPath($factory->getFilename()) === $selectedFactory);

        return $this->generateFullyQualifiedClassStringFromPath($factory->getPathname());
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

    protected function generateFullyQualifiedClassStringFromPath(string $path): string
    {
        return Str::of($this->generateNamespaceFromPath($path))
            // ->prepend('\\')
            ->remove('.php');
        // ->append('::class');
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

    protected function selectAmount(string $label): int
    {
        return text(
            label: $label,
            default: 1,
            validate: fn (string $value) => match (true) {
                ! is_numeric($value) => 'The value must be a number.',
                $value < 1 => 'The value must be at least 1.',
                default => null
            }
        );
    }
}
