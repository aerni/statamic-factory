<?php

namespace Aerni\Factory\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
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
    protected $signature = 'statamic:make:seeder';

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
        $factories = collect(File::allFiles(base_path('database/factories/statamic')));

        $selectedFactory = select(
            label: 'Select the factory you want to create a seeder for.',
            options: $factories->map->getRelativePathName(),
        );

        $factory = $factories->firstWhere(fn ($factory) => $factory->getRelativePathName() === $selectedFactory);

        $classNamespace = Str::replace('Factories', 'Seeders', $this->generateNamespaceFromPath($factory->getPath()));
        $factoryClassName = Str::remove('.php', $factory->getFilename());
        $useFactory = $this->generateNamespaceFromPath($factory->getPath()).'\\'.$factoryClassName;
        $className = Str::remove('Factory.php', $factory->getFilename());

        $classPath = $this->generatePathFromNamespace($classNamespace)."{$className}Seeder.php";

        if (File::exists($classPath) && ! confirm(label: 'This seeder already exists. Do you want to override it?', default: false)) {
            return;
        }

        $stub = preg_replace(
            ['/\{{ classNamespace \}}/', '/\{{ useFactory \}}/', '/\{{ className \}}/', '/\{{ factoryClassName \}}/'],
            [$classNamespace, $useFactory, $className, $factoryClassName],
            File::get(__DIR__.'/stubs/seeder.stub')
        );

        File::ensureDirectoryExists(dirname($classPath));
        File::put($classPath, $stub);

        info("The seeder was successfully created: <comment>{$this->getRelativePath($classPath)}</comment>");
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

    protected function getRelativePath(string $path): string
    {
        return str_replace(base_path().'/', '', $path);
    }
}
