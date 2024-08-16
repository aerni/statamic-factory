<?php

namespace Aerni\Factory\Console\Commands;

use Illuminate\Support\Str;
use Statamic\Facades\Taxonomy;
use Illuminate\Console\Command;
use Statamic\Facades\Collection;
use function Laravel\Prompts\info;
use Statamic\Console\RunsInPlease;
use function Laravel\Prompts\select;

use Illuminate\Support\Facades\File;
use function Laravel\Prompts\confirm;
use Illuminate\Support\Facades\Process;
use Aerni\Factory\Factories\DefinitionGenerator;
use Aerni\Factory\Factories\Factory;

class MakeFactory extends Command
{
    use RunsInPlease;

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
    protected $description = 'Create a new Statamic factory class';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $factory = $this->getFactoryClassData();

        $fileExists = File::exists($factory['path']);

        if ($fileExists && ! confirm(
            label: "This factory already exists. Do you want to update the factory's definition method?",
            yes: 'Yes, update the existing factory.',
            no: 'No, abort.',
            default: false
        )) {
            return;
        }

        $fileContents = $fileExists
            ? $this->updateDefinitionOfExistingFactory(File::get($factory['path']), $factory)
            : $this->generateFactoryFromStub($factory);

        File::ensureDirectoryExists(dirname($factory['path']));

        File::put($factory['path'], $fileContents);

        Process::run('./vendor/bin/pint ' . $factory['path']);

        $fileExists
            ? info("The factory was successfully updated: <comment>{$this->getRelativePath($factory['path'])}</comment>")
            : info("The factory was successfully created: <comment>{$this->getRelativePath($factory['path'])}</comment>");
    }

    protected function getFactoryClassData(): array
    {
        $factoryType = select(
            label: 'For which content type do you want to create a factory?',
            options: [
                'entry' => 'Entry',
                'term' => 'Term',
            ],
            validate: fn (string $value) => match ($value) {
                'entry' => Collection::all()->isEmpty()
                    ? 'You need to create at least one collection to create a factory.'
                    : null,
                'term' => Taxonomy::all()->isEmpty()
                    ? 'You need to create at least one taxonomy to create a factory.'
                    : null,
            },
        );

        $models = match ($factoryType) {
            'entry' => Collection::all(),
            'term' => Taxonomy::all(),
        };

        $repository = match ($factoryType) {
            'entry' => 'collections',
            'term' => 'taxonomies',
        };

        $selectedModel = select(
            label: 'For which ' . Str::singular($repository) . ' do you want to create a factory?',
            options: $models->mapWithKeys(fn ($model) => [$model->handle() => $model->title()]),
        );

        $model = $models->firstWhere('handle', $selectedModel);

        $blueprints = match ($factoryType) {
            'entry' => $model->entryBlueprints(),
            'term' => $model->termBlueprints(),
        };

        $selectedBlueprint = select(
            label: 'For which blueprint do you want to create a factory?',
            options: $blueprints->mapWithKeys(fn ($blueprint) => [$blueprint->handle() => $blueprint->title()]),
        );

        $blueprint = $blueprints->firstWhere('handle', $selectedBlueprint);

        $namespace = Factory::$namespace.collect([$repository, $selectedModel])->map(ucfirst(...))->implode('\\');

        $class = ucfirst($blueprint);

        return [
            'namespace' => $namespace,
            'class' => $class,
            'definition' => new DefinitionGenerator($blueprint),
            'path' => "{$this->generatePathFromNamespace($namespace)}/{$class}Factory.php",
        ];
    }

    protected function updateDefinitionOfExistingFactory(string $fileContents, array $replacements): string
    {
        $definition = $replacements['definition'];

        return preg_replace(
            '/public function definition\(\): array\s*{\s*return \[.*?\];\s*}/s',
            "public function definition(): array\n{\n    $definition\n}",
            $fileContents,
        );
    }

    protected function generateFactoryFromStub(array $replacements): string
    {
        return preg_replace(
            ['/\{{ classNamespace \}}/', '/\{{ className \}}/', '/\{{ definition \}}/'],
            [$replacements['namespace'], $replacements['class'], $replacements['definition']],
            File::get(__DIR__.'/stubs/factory.stub')
        );
    }

    protected function generatePathFromNamespace(string $namespace): string
    {
        $path = str($namespace)->replace('\\', '/')->lower();

        return base_path($path);
    }

    protected function getRelativePath(string $path): string
    {
        return str_replace(base_path().'/', '', $path);
    }
}
