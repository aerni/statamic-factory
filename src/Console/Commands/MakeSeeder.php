<?php

namespace Aerni\Factory\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Statamic\Console\RunsInPlease;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;

class MakeSeeder extends Command
{
    use RunsInPlease;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statamic:make:seeder {factory?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new seeder class for a Statamic factory';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $seeder = $this->argument('factory')
            ? $this->getSeederClassDataFromFactoryClass()
            : $this->getSeederClassData();

        if (File::exists($seeder['path']) && ! confirm(
            label: 'This seeder already exists. Do you want to override it?',
            yes: 'Yes, override.',
            no: 'No, abort.',
            default: false
        )) {
            return;
        }

        $fileContents = $this->generateSeederFromStub($seeder);

        $this->saveFile($seeder['path'], $fileContents);

        info("The seeder was successfully created: <comment>{$this->getRelativePath($seeder['path'])}</comment>");
    }

    protected function getSeederClassDataFromFactoryClass()
    {
        $factory = $this->argument('factory');

        $classNamespace = Str::of($factory)->beforeLast('\\')->replace('Factories', 'Seeders');
        $factoryClassName = Str::of($factory)->afterLast('\\')->append('Factory');
        $factoryClassImport = "{$factory}Factory";
        $className = Str::remove('Factory', $factoryClassName);

        return [
            'classNamespace' => $classNamespace,
            'className' => $className,
            'factoryClassImport' => $factoryClassImport,
            'factoryClassName' => $factoryClassName,
            'path' => "{$this->generatePathFromNamespace($classNamespace)}/{$className}Seeder.php",
        ];
    }

    protected function getSeederClassData(): array
    {
        $factories = collect(File::allFiles(base_path('database/factories/statamic')));

        // TODO: Show namespace, not path.
        $selectedFactory = select(
            label: 'For which factory do you want to create a seeder?',
            options: $factories->map->getRelativePathName(),
        );

        $factory = $factories->firstWhere(fn ($factory) => $factory->getRelativePathName() === $selectedFactory);

        $classNamespace = Str::replace('Factories', 'Seeders', $this->generateNamespaceFromPath($factory->getPath()));
        $factoryClassName = Str::remove('.php', $factory->getFilename());
        $factoryClassImport = $this->generateNamespaceFromPath($factory->getPath()).'\\'.$factoryClassName;
        $className = Str::remove('Factory.php', $factory->getFilename());

        return [
            'classNamespace' => $classNamespace,
            'className' => $className,
            'factoryClassImport' => $factoryClassImport,
            'factoryClassName' => $factoryClassName,
            'path' => "{$this->generatePathFromNamespace($classNamespace)}/{$className}Seeder.php",
        ];
    }

    protected function generateSeederFromStub(array $replacements): string
    {
        return preg_replace(
            ['/\{{ classNamespace \}}/', '/\{{ className \}}/', '/\{{ factoryClassImport \}}/', '/\{{ factoryClassName \}}/'],
            [$replacements['classNamespace'], $replacements['className'], $replacements['factoryClassImport'], $replacements['factoryClassName']],
            File::get(__DIR__.'/stubs/seeder.stub')
        );
    }

    protected function generatePathFromNamespace(string $namespace): string
    {
        $path = collect(explode('\\', $namespace))
            ->map(fn ($value) => Str::snake($value))
            ->implode('/');

        return base_path($path);
    }

    protected function generateNamespaceFromPath(string $path): string
    {
        return collect(explode('/', $this->getRelativePath($path)))
            ->map(Str::studly(...))
            ->implode('\\');
    }

    protected function getRelativePath(string $path): string
    {
        return str_replace(base_path().'/', '', $path);
    }

    protected function saveFile(string $path, string $contents): void
    {
        File::ensureDirectoryExists(dirname($path));

        File::put($path, $contents);

        Process::run('./vendor/bin/pint '.$path);
    }
}
