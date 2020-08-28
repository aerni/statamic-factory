<?php

namespace Aerni\Factory\Commands;

use Aerni\Factory\Factory;
use Statamic\Facades\Taxonomy;
use Illuminate\Console\Command;
use Statamic\Facades\Blueprint;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Collection;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\AssetContainer;
use Illuminate\Support\Facades\Validator;

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
            ['Collection Entry', 'Taxonomy Term']
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

        if ($contentType === 'Collection Entry') {
            $contentHandle = $this->choice('Choose a collection', $this->collections());
            $blueprintHandle = $this->choice('Choose the blueprint for your entries', $this->blueprints("collections/$contentHandle"));
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

        if ($contentType === 'Taxonomy Term') {
            $contentHandle = $this->choice('Choose a taxonomy', $this->taxonomies());
            $blueprintHandle = $this->choice('Choose the blueprint for your terms', $this->blueprints("taxonomies/$contentHandle"));
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
            $this->info('The factory was successfull!');
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
        $collections = Collection::handles()->all();

        if (empty($collections)) {
            $this->error('You have no collections. Create at least one collection to use the factory.');
        }

        return $collections;
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
        $taxonomies = Taxonomy::handles()->all();

        if (empty($taxonomies)) {
            $this->error('You have no taxonomies. Create at least one taxonomy to use the factory.');
        }

        return $taxonomies;
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
     * Get blueprint handles
     *
     * @return array
     */
    protected function blueprints(string $path): array
    {
        $blueprints = Blueprint::in($path)->keys()->all();

        if (empty($blueprints)) {
            $this->error("No blueprint found in $path");
        }

        return $blueprints;
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
