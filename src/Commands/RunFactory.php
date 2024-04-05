<?php

namespace Aerni\Factory\Commands;

use Facades\Aerni\Factory\Factory;
use Statamic\Facades\Taxonomy;
use Illuminate\Console\Command;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Collection;
use function Laravel\Prompts\text;
use Statamic\Console\RunsInPlease;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use Illuminate\Support\Collection as LaravelCollection;

class RunFactory extends Command
{
    use RunsInPlease;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statamic:factory';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate content with the factory';

    public function handle(): void
    {
        $type = select(
            label: 'Select the type of content you want to create.',
            options: [
                'entry' => 'Entry',
                'term' => 'Term',
                'global' => 'Global'
            ],
            validate: fn (string $value) => match ($value) {
                'entry' => Collection::all()->isEmpty()
                    ? 'You need to create at least one collection to use the factory.'
                    : null,
                'term' => Taxonomy::all()->isEmpty()
                    ? 'You need to create at least one taxonomy to use the factory.'
                    : null,
                'global' => GlobalSet::all()->isEmpty()
                    ? 'You need to create at least one global set to use the factory.'
                    : null,
            },
        );

        match ($type) {
            'entry' => $this->runEntryFactory(),
            'term' => $this->runTermFactory(),
            'global' => $this->runGlobalFactory(),
        };

        info('The content was successfully created!');
    }

    protected function runEntryFactory(): void
    {
        $collections = Collection::all();

        $handle = $this->selectContent('For which collection do you want to create entries?', $collections);

        $blueprint = select(
            label: 'Select the blueprint to use for creating the entries.',
            options: $collections->firstWhere('handle', $handle)
                ->entryBlueprints()
                ->mapWithKeys(fn ($blueprint) => [$blueprint->handle() => $blueprint->title()]),
        );

        $amount = $this->selectAmount('How many entries do you want to create?');

        Factory::run('entry', $handle, $blueprint, $amount);
    }

    protected function runTermFactory(): void
    {
        $taxonomies = Taxonomy::all();

        $handle = $this->selectContent('For which taxonomy do you want to create terms?', $taxonomies);

        $blueprint = select(
            label: 'Select the blueprint to use for creating the terms.',
            options: $taxonomies->firstWhere('handle', $handle)
                ->termBlueprints()
                ->mapWithKeys(fn ($blueprint) => [$blueprint->handle() => $blueprint->title()]),
        );

        $amount = $this->selectAmount('How many terms do you want to create?');

        Factory::run('term', $handle, $blueprint, $amount);
    }

    protected function runGlobalFactory(): void
    {
        $globals = GlobalSet::all();

        $handle = $this->selectContent('On which global set do you want to run the factory?', $globals);

        $blueprint = $globals->firstWhere(fn ($global) => $global->handle() === $handle)->blueprint();

        if (is_null($blueprint)) {
            error("The selected global set has no blueprint. Create a blueprint to use the factory.");
            exit;
        }

        Factory::run('global', $handle, $blueprint, 1);
    }

    protected function selectContent(string $label, LaravelCollection $options): string
    {
        return select(
            label: $label,
            options: $options->mapWithKeys(fn ($option) => [$option->handle() => $option->title()]),
        );
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
