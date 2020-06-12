<?php

namespace Aerni\Factory\Commands;

use Aerni\Factory\Factory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Taxonomy;

class RunFactory extends Command
{
    use RunsInPlease;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statamic:factory:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate fake content with the factory';

    /**
     * The factory instance.
     *
     * @var Factory
     */
    protected $factory;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Factory $factory)
    {
        parent::__construct();

        $this->factory = $factory;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $contentType = $this->choice(
            'Choose the type of content you want to create',
            ['Collection', 'Taxonomy']
        );

        // if ($contentType === 'Asset' && $this->hasAssetContainers()) {
        //     $contentHandle = $this->choice('Choose an asset container', $this->assetContainers());
        //     $blueprintHandle = $this->assetBlueprint($contentHandle);
        //     $amount = $this->askValid(
        //         "How many assets do you want to create?",
        //         'amount',
        //         ['required', 'numeric', 'min:1']
        //     );
        // }
        
        if ($contentType === 'Collection' && $this->hasCollections()) {
            $contentHandle = $this->choice('Choose a collection', $this->collections());
            $blueprintHandle = $this->choice('Choose the blueprint for your entries', $this->collectionBlueprints($contentHandle));
            $amount = $this->askValid(
                'How many entries do you want to create?',
                'amount',
                ['required', 'numeric', 'min:1']
            );
        }

        // if ($contentType === 'Global' && $this->hasGlobals()) {
        //     $contentHandle = $this->choice('Choose a global set', $this->globals());
        //     $blueprintHandle = $this->globalBlueprint($contentHandle);
        //     $amount = 1;
        // }

        if ($contentType === 'Taxonomy' && $this->hasTaxonomies()) {
            $contentHandle = $this->choice('Choose a taxonomy', $this->taxonomies());
            $blueprintHandle = $this->choice('Choose the blueprint for your terms', $this->taxonomyBlueprints($contentHandle));
            $amount = $this->askValid(
                'How many terms do you want to create?',
                'amount',
                ['required', 'numeric', 'min:1']
            );
        }
        
        $this->runFactory($contentType, $contentHandle, $blueprintHandle, $amount);
    }

    /**
     * Run the factory.
     *
     * @param string $contentType
     * @param string $contentHandle
     * @param string $blueprintHandle
     * @param string $amount
     * @return void
     */
    protected function runFactory(string $contentType, string $contentHandle, string $blueprintHandle, string $amount): void
    {
        try {
            $this->factory->run($contentType, $contentHandle, $blueprintHandle, $amount);
            $this->info('🎉 The factory was successfull!');
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }
    }

    /**
     * Get the available asset container handles.
     *
     * @return array
     */
    protected function assetContainers(): array
    {
        return AssetContainer::all()->map(function ($container) {
            return $container->handle();
        })->toArray();
    }

    /**
     * Get the available collection handles.
     *
     * @return array
     */
    protected function collections(): array
    {
        return Collection::handles()->toArray();
    }

    /**
     * Get the available global handles.
     *
     * @return array
     */
    protected function globals(): array
    {
        return GlobalSet::all()->map(function ($container) {
            return $container->handle();
        })->toArray();
    }

    /**
     * Get the available taxonomy handles.
     *
     * @return array
     */
    protected function taxonomies(): array
    {
        return Taxonomy::handles()->toArray();
    }

    /**
     * Get the blueprint handle of an asset container.
     *
     * @return string
     */
    protected function assetBlueprint(string $contentHandle): string
    {
        return AssetContainer::find($contentHandle)->blueprint();
    }

    /**
     * Get the blueprint handles of a collection.
     *
     * @return array
     */
    protected function collectionBlueprints(string $contentHandle): array
    {
        return Collection::find($contentHandle)
            ->entryBlueprints()
            ->map(function ($blueprint) {
                return $blueprint->handle();
            })
            ->toArray();
        ;
    }

    /**
     * Get the blueprint handle of a global set.
     *
     * @return string
     */
    protected function globalBlueprint(string $contentHandle): string
    {
        return GlobalSet::find($contentHandle)->blueprint();
    }

    /**
     * Get the blueprint handles of a taxonomy.
     *
     * @return array
     */
    protected function taxonomyBlueprints(string $contentHandle): array
    {
        return Taxonomy::find($contentHandle)
            ->termBlueprints()
            ->map(function ($blueprint) {
                return $blueprint->handle();
            })
            ->toArray();
        ;
    }

    /**
     * Check if there's any asset containers.
     *
     * @return bool
     */
    protected function hasAssetContainers(): bool
    {
        if (empty($this->assetContainers())) {
            $this->error('You have no asset containers. Create at least one asset container to use the factory.');

            return false;
        }

        return true;
    }

    /**
     * Check if there's any collections.
     *
     * @return bool
     */
    protected function hasCollections(): bool
    {
        if (empty($this->collections())) {
            $this->error('You have no collections. Create at least one collection to use the factory.');

            return false;
        }

        return true;
    }

    /**
     * Check if there's any global sets.
     *
     * @return bool
     */
    protected function hasGlobals(): bool
    {
        if (empty($this->globals())) {
            $this->error('You have no globals. Create at least one global set to use the factory.');

            return false;
        }

        return true;
    }

    /**
     * Check if there's any taxonomies.
     *
     * @return bool
     */
    protected function hasTaxonomies(): bool
    {
        if (empty($this->taxonomies())) {
            $this->error('You have no taxonomies. Create at least one taxonomy to use the factory.');

            return false;
        }

        return true;
    }

    /**
     * Validate the answer of a question.
     *
     * @param string $question
     * @param string $field
     * @param array $rules
     * @return string
     */
    protected function askValid(string $question, string $field, array $rules): string
    {
        $value = $this->ask($question);

        if ($message = $this->validateInput($rules, $field, $value)) {
            $this->error($message);

            return $this->askValid($question, $field, $rules);
        }

        return $value;
    }

    /**
     * Validate the input.
     *
     * @param array $rules
     * @param string $fieldName
     * @param string $value
     * @return mixed
     */
    protected function validateInput(array $rules, string $fieldName, string $value)
    {
        $validator = Validator::make([
            $fieldName => $value,
        ], [
            $fieldName => $rules,
        ]);

        return $validator->fails()
            ? $validator->errors()->first($fieldName)
            : null;
    }
}
