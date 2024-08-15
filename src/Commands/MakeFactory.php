<?php

namespace Aerni\Factory\Commands;

use Aerni\Factory\Factories\DefinitionGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\Collection;
use Statamic\Facades\Taxonomy;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;

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
    protected $description = 'Generate a factory from a blueprint';

    public function handle()
    {
        $type = select(
            label: 'Select the type of factory you want to create.',
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

        $model = $this->getModelData($type);

        $classNamespace = 'Database\\Factories\\Statamic\\'.collect([$model['repository'], $model['type']])->map(ucfirst(...))->implode('\\');
        $className = ucfirst($model['blueprint']);
        $definition = new DefinitionGenerator($model['blueprint']);

        $classPath = $this->generatePathFromNamespace($classNamespace)."{$className}Factory.php";

        if (File::exists($classPath) && ! confirm(label: 'This factory already exists. Do you want to update the definition?', default: false)) {
            return;
        }

        if (File::exists($classPath)) {
            $stub = preg_replace(
                '/public function definition\(\): array\s*{\s*return \[.*?\];\s*}/s',
                "public function definition(): array\n{\n    $definition\n}",
                File::get($classPath)
            );
        } else {
            $stub = preg_replace(
                ['/\{{ classNamespace \}}/', '/\{{ className \}}/', '/\{{ definition \}}/'],
                [$classNamespace, $className, $definition],
                File::get(__DIR__.'/stubs/factory.stub')
            );
        }

        File::ensureDirectoryExists(dirname($classPath));
        File::put($classPath, $stub);
        Process::run("./vendor/bin/pint $classPath");

        info("The factory was successfully created: <comment>{$this->getRelativePath($classPath)}</comment>");
    }

    protected function getModelData(string $type): array
    {
        $models = match ($type) {
            'entry' => Collection::all(),
            'term' => Taxonomy::all(),
        };

        $selectedModel = select(
            label: 'Select the model of the factory.',
            options: $models->mapWithKeys(fn ($model) => [$model->handle() => $model->title()]),
        );

        $model = $models->firstWhere('handle', $selectedModel);

        $blueprints = match (true) {
            ($type === 'entry') => $model->entryBlueprints(),
            ($type === 'term') => $model->termBlueprints(),
        };

        $selectedBlueprint = select(
            label: 'Select the blueprint of the factory.',
            options: $blueprints->mapWithKeys(fn ($blueprint) => [$blueprint->handle() => $blueprint->title()]),
        );

        $blueprint = $blueprints->firstWhere('handle', $selectedBlueprint);

        return [
            'repository' => ($type === 'entry') ? 'collections' : 'taxonomies',
            'type' => $selectedModel,
            'blueprint' => $blueprint,
        ];
    }

    protected function generatePathFromNamespace(string $namespace): string
    {
        $name = str($namespace)->finish('\\')->replaceFirst(app()->getNamespace(), '')->lower();

        return base_path(str_replace('\\', '/', $name));
    }

    protected function getRelativePath(string $path): string
    {
        return str_replace(base_path().'/', '', $path);
    }
}
