<?php

namespace Aerni\Factory\Console\Commands;

use Illuminate\Console\Command;
use function Laravel\Prompts\select;

use Statamic\Console\RunsInPlease;
use Illuminate\Support\Facades\File;
use Illuminate\Console\ConfirmableTrait;
use SplFileInfo;

class Seed extends Command
{
    use ConfirmableTrait;
    use RunsInPlease;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statamic:seed
        {--class=Database\\Seeders\\StatamicSeeder : The class name of the root seeder}
        {--force : Force the operation to run when in production}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed Statamic with content';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (! $this->confirmToProceed()) {
            return 1;
        }

        $this->components->info('Seeding Statamic with content.');

        $this->getSeeder()->__invoke();

        return 0;
    }

    protected function getSeeder()
    {
        $class = $this->option('class');

        if ($class !== 'Database\\Seeders\\StatamicSeeder') {
            $class = $this->guessClass($class);
        }

        return $this->laravel->make($class)
            ->setContainer($this->laravel)
            ->setCommand($this);
    }

    protected function guessClass(string $class): string
    {
        $files = collect(File::allFiles(database_path('seeders/Statamic')))
            ->where(fn ($file) => str($file->getRelativePathName())->replace('/', '\\')->contains($class));

        if ($files->isEmpty()) {
            return $class;
        }

        if ($files->count() > 1) {
            return select(
                'Multiple seeders found. Which one do you want to run?',
                $files->mapWithKeys(fn ($file) => [$this->getNamespaceFromFile($file) => $this->getNamespaceFromFile($file)])
            );
        }

        return $this->getNamespaceFromFile($files->first());
    }

    protected function getNamespaceFromFile(SplFileInfo $file): string
    {
        return str($file->getRelativePathname())
            ->replace('/', '\\')
            ->prepend('Database\\Seeders\\Statamic\\')
            ->remove('.php');
    }
}
