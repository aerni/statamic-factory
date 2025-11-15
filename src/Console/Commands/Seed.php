<?php

namespace Aerni\Factory\Console\Commands;

use Aerni\Factory\Console\Commands\Concerns\ReadsClassFromFile;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Statamic\Console\RunsInPlease;
use Symfony\Component\Finder\SplFileInfo;

use function Laravel\Prompts\multiselect;

class Seed extends Command
{
    use ConfirmableTrait;
    use ReadsClassFromFile;
    use RunsInPlease;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statamic:seed
        {--all : Run all available Statamic seeders}
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
    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return Command::FAILURE;
        }

        $this->seeders()->each($this->runSeeder(...));

        return Command::SUCCESS;
    }

    protected function seeders(): Collection
    {
        $seeders = $this->discoverSeeders();

        if ($seeders->isEmpty()) {
            $this->fail('No Statamic seeders found. Run "php please make:seeder" first.');
        }

        if ($this->option('all')) {
            return $seeders->keys();
        }

        return collect(multiselect(
            label: 'Which seeders would you like to run?',
            options: $seeders,
            required: 'Select at least one seeder'
        ));
    }

    protected function discoverSeeders(): Collection
    {
        $seedersPath = database_path('seeders/Statamic');

        if (! File::exists($seedersPath)) {
            return collect();
        }

        return collect(File::allFiles($seedersPath))
            ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'php')
            ->mapWithKeys(fn (SplFileInfo $file) => [$this->getClassName($file) => $this->getClassDisplay($file)])
            ->sort();
    }

    protected function runSeeder(string $class): void
    {
        $seeder = $this->laravel->make($class)
            ->setContainer($this->laravel)
            ->setCommand($this);

        $this->components->task(
            'Running '.Str::afterLast($class, '\\'),
            fn () => $seeder->__invoke()
        );
    }
}
