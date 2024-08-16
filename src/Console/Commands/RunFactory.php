<?php

namespace Aerni\Factory\Console\Commands;

use Aerni\Factory\Factories\EntryFactory;
use Aerni\Factory\Factories\GlobalFactory;
use Aerni\Factory\Factories\TermFactory;
use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\Collection;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Taxonomy;

use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

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
                'global' => 'Global',
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

        $selectedCollection = select(
            label: 'Select the collection for which you want to create entries.',
            options: $collections->mapWithKeys(fn ($collection) => [$collection->handle() => $collection->title()]),
        );

        $selectedBlueprint = select(
            label: 'Select the blueprint to use for creating the entries.',
            options: $collections->firstWhere('handle', $selectedCollection)
                ->entryBlueprints()
                ->mapWithKeys(fn ($blueprint) => [$blueprint->handle() => $blueprint->title()]),
        );

        $collection = $collections->firstWhere('handle', $selectedCollection);

        $blueprint = $collection->entryBlueprints()->firstWhere('handle', $selectedBlueprint);

        (new EntryFactory($collection, $blueprint))
            ->run($this->selectAmount('How many entries do you want to create?'));
    }

    protected function runTermFactory(): void
    {
        $taxonomies = Taxonomy::all();

        $selectedTaxonomy = select(
            label: 'Select the taxonomy for which you want to create terms.',
            options: $taxonomies->mapWithKeys(fn ($taxonomy) => [$taxonomy->handle() => $taxonomy->title()]),
        );

        $selectedBlueprint = select(
            label: 'Select the blueprint to use for creating the terms.',
            options: $taxonomies->firstWhere('handle', $selectedTaxonomy)
                ->termBlueprints()
                ->mapWithKeys(fn ($blueprint) => [$blueprint->handle() => $blueprint->title()]),
        );

        $taxonomy = $taxonomies->firstWhere('handle', $selectedTaxonomy);

        $blueprint = $taxonomy->termBlueprints()->firstWhere('handle', $selectedBlueprint);

        (new TermFactory($taxonomy, $blueprint))
            ->run($this->selectAmount('How many terms do you want to create?'));
    }

    protected function runGlobalFactory(): void
    {
        $globals = GlobalSet::all();

        $selectedGlobal = select(
            label: 'Select the global set you want to run the factory on.',
            options: $globals->mapWithKeys(fn ($global) => [$global->handle() => $global->title()]),
            validate: fn (string $value) => $globals->firstWhere(fn ($global) => $global->handle() === $value)->blueprint() === null
                    ? 'The selected global set has no blueprint. Create a blueprint to use the factory.'
                    : null,
        );

        $global = $globals->firstWhere(fn ($global) => $global->handle() === $selectedGlobal);

        (new GlobalFactory($global))->run();
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
