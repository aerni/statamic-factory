<?php

namespace Aerni\Factory\Console\Commands;

use Aerni\Factory\Console\Commands\Concerns\GetsRelativePath;
use Aerni\Factory\Console\Commands\Concerns\SavesFile;
use Aerni\Factory\Factories\Factory;
use Aerni\Factory\Factories\FactoryDefinitionGenerator;
use Aerni\Factory\Factories\UserFactoryDefinitionGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Statamic\Console\RunsInPlease;
use Statamic\Contracts\Auth\User as AuthUser;
use Statamic\Contracts\Entries\Collection as EntriesCollection;
use Statamic\Contracts\Taxonomies\Taxonomy as TaxonomiesTaxonomy;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Events\TermBlueprintFound;
use Statamic\Events\UserBlueprintFound;
use Statamic\Facades\Collection;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\User;
use Statamic\Fields\Blueprint;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;

class MakeFactory extends Command
{
    use GetsRelativePath;
    use RunsInPlease;
    use SavesFile;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statamic:make:factory';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Statamic factory';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $factory = $this->getFactoryClassData();

        File::exists($factory['path'])
            ? $this->handleUpdateOfExistingFactory($factory)
            : $this->handleGenerationOfNewFactory($factory);

        if ($factory['createSeeder']) {
            $this->call(MakeSeeder::class, ['factory' => "{$factory['classNamespace']}\\{$factory['className']}"]);
        }
    }

    protected function handleGenerationOfNewFactory(array $factory): void
    {
        $fileContents = $this->generateFactoryFromStub($factory);

        $this->saveFile($factory['path'], $fileContents);

        info("The factory was successfully created: <comment>{$this->getRelativePath($factory['path'])}</comment>");
    }

    protected function handleUpdateOfExistingFactory(array $factory): void
    {
        $shouldUpdateDefinition = confirm(
            label: 'This factory already exists. Do you want to update its definition method?',
            yes: 'Yes, update the definition.',
            no: 'No, abort.',
            default: false
        );

        if (! $shouldUpdateDefinition) {
            return;
        }

        $fileContents = $this->updateDefinitionOfExistingFactory(File::get($factory['path']), $factory);

        $this->saveFile($factory['path'], $fileContents);

        info("The factory was successfully updated: <comment>{$this->getRelativePath($factory['path'])}</comment>");
    }

    protected function getFactoryClassData(): array
    {
        $contentType = select(
            label: 'For which content type do you want to create a factory?',
            options: [
                'collections' => 'Collections',
                'taxonomies' => 'Taxonomies',
                'users' => 'Users',
            ],
            validate: fn (string $value) => match ($value) {
                'collections' => Collection::all()->isEmpty()
                    ? 'You need to create at least one collection to create a factory.'
                    : null,
                'taxonomies' => Taxonomy::all()->isEmpty()
                    ? 'You need to create at least one taxonomy to create a factory.'
                    : null,
                default => null,
            },
        );

        $contentModel = $this->selectContentModel($contentType);

        $blueprint = $this->selectBlueprint($contentModel);

        $this->fireBlueprintEvent($blueprint);

        $classNamespace = collect([$contentType])
            ->when($contentType !== 'users', fn ($segments) => $segments->push($contentModel->handle()))
            ->map(Str::studly(...))
            ->prepend(Str::replaceLast('\\', '', Factory::$namespace))
            ->implode('\\');

        $className = str($blueprint)->studly()->append('Factory');

        $createSeeder = confirm(
            label: 'Do you also want to create a seeder for this factory?',
            yes: 'Yes, please.',
            no: 'No, thanks.',
            default: true,
        );

        return [
            'classNamespace' => $classNamespace,
            'className' => $className,
            'definition' => match ($contentType) {
                'users' => new UserFactoryDefinitionGenerator($blueprint),
                default => new FactoryDefinitionGenerator($blueprint),
            },
            'path' => $this->generatePathFromNamespace("$classNamespace\\$className"),
            'createSeeder' => $createSeeder,
            'trait' => match ($contentType) {
                'collections' => 'CreatesEntry',
                'taxonomies' => 'CreatesTerm',
                'users' => 'CreatesUser',
            },
        ];
    }

    protected function selectContentModel(string $contentType): mixed
    {
        if ($contentType === 'users') {
            return User::make();
        }

        $contentModels = match ($contentType) {
            'collections' => Collection::all(),
            'taxonomies' => Taxonomy::all(),
        };

        $selectedContentModel = select(
            label: 'For which '.Str::singular($contentType).' do you want to create a factory?',
            options: $contentModels->mapWithKeys(fn ($model) => [$model->handle() => $model->title()]),
        );

        return $contentModels->firstWhere('handle', $selectedContentModel);
    }

    protected function selectBlueprint($contentModel): Blueprint
    {
        $blueprints = match (true) {
            $contentModel instanceof EntriesCollection => $contentModel->entryBlueprints(),
            $contentModel instanceof TaxonomiesTaxonomy => $contentModel->termBlueprints(),
            $contentModel instanceof AuthUser => collect([$contentModel->blueprint()]),
        };

        if ($blueprints->count() === 1) {
            return $blueprints->first();
        }

        $selectedBlueprint = select(
            label: 'For which blueprint do you want to create a factory?',
            options: $blueprints->mapWithKeys(fn ($blueprint) => [$blueprint->handle() => $blueprint->title()]),
        );

        return $blueprints->firstWhere('handle', $selectedBlueprint);
    }

    protected function fireBlueprintEvent($blueprint): void
    {
        match (true) {
            Str::contains($blueprint->namespace(), 'collections') => EntryBlueprintFound::dispatch($blueprint),
            Str::contains($blueprint->namespace(), 'taxonomies') => TermBlueprintFound::dispatch($blueprint),
            is_null($blueprint->namespace()) && $blueprint->handle() === 'user' => UserBlueprintFound::dispatch($blueprint),
            default => null,
        };
    }

    protected function generateFactoryFromStub(array $replacements): string
    {
        return preg_replace(
            ['/\{{ classNamespace \}}/', '/\{{ className \}}/', '/\{{ trait \}}/', '/\{{ definition \}}/'],
            [$replacements['classNamespace'], $replacements['className'], $replacements['trait'], $replacements['definition']],
            File::get(__DIR__.'/stubs/factory.stub')
        );
    }

    protected function updateDefinitionOfExistingFactory(string $fileContents, array $replacements): string
    {
        $definition = $replacements['definition'];

        return preg_replace(
            '/public function definition\(\): array\s*{\s*return \[.*?\];\s*}/s',
            "public function definition(): array\n{\n    return $definition;\n}",
            $fileContents,
        );
    }

    protected function generatePathFromNamespace(string $namespace): string
    {
        $relativePath = str($namespace)
            ->remove('Database\\Factories\\')
            ->replace('\\', '/');

        return database_path("factories/{$relativePath}.php");
    }
}
