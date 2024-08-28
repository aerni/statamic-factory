<?php

namespace Aerni\Factory\Console\Commands;

use Aerni\Factory\Console\Commands\Concerns\GetsRelativePath;
use Aerni\Factory\Console\Commands\Concerns\SavesFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Statamic\Console\RunsInPlease;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;

class MakeSeeder extends Command
{
    use GetsRelativePath;
    use RunsInPlease;
    use SavesFile;

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
        if (! $this->hasFactories()) {
            return info('There are no Statamic factories. To create a seeder, you need to create a factory first.');
        }

        $seeder = $this->argument('factory')
            ? $this->getSeederClassDataFromFactory($this->argument('factory'))
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

    protected function getSeederClassDataFromFactory(string $factory): array
    {
        $classNamespace = Str::of($factory)->beforeLast('\\')->replace('Factories', 'Seeders');
        $className = Str::of($factory)->afterLast('\\')->replace('Factory', 'Seeder');
        $factoryClassName = Str::of($factory)->afterLast('\\');

        return [
            'classNamespace' => $classNamespace,
            'className' => $className,
            'factoryClassImport' => $factory,
            'factoryClassName' => $factoryClassName,
            'path' => $this->generatePathFromNamespace("$classNamespace\\$className"),
        ];
    }

    protected function getSeederClassData(): array
    {
        $factories = collect(File::allFiles(database_path('factories/Statamic')));

        $selectedFactory = select(
            label: 'For which factory do you want to create a seeder?',
            options: $factories->mapWithKeys(fn ($factory) => [
                $factory->getRelativePathname() => $this->generateNamespaceFromPath($factory->getRelativePathName()),
            ]),
        );

        $factory = $factories->firstWhere(fn ($factory) => $factory->getRelativePathName() === $selectedFactory);

        $classNamespace = Str::replace('Factories', 'Seeders', $this->generateNamespaceFromPath($factory->getPath()));
        $factoryClassName = Str::remove('.php', $factory->getFilename());
        $factoryClassImport = $this->generateNamespaceFromPath($factory->getPath()).'\\'.$factoryClassName;
        $className = str($factory->getFilename())->remove('Factory.php')->append('Seeder');

        return [
            'classNamespace' => $classNamespace,
            'className' => $className,
            'factoryClassImport' => $factoryClassImport,
            'factoryClassName' => $factoryClassName,
            'path' => $this->generatePathFromNamespace("$classNamespace\\$className"),
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
        $relativePath = str($namespace)
            ->remove('Database\\Seeders\\')
            ->replace('\\', '/');

        return database_path("seeders/{$relativePath}.php");
    }

    protected function generateNamespaceFromPath(string $path): string
    {
        return collect(explode('/', $this->getRelativePath($path)))
            ->map(Str::studly(...))
            ->map(fn ($value) => Str::remove('.php', $value))
            ->implode('\\');
    }

    protected function hasFactories(): bool
    {
        return File::isDirectory(database_path('factories/Statamic'))
            && ! empty(File::allFiles(database_path('factories/Statamic')));
    }
}
