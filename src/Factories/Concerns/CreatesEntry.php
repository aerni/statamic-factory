<?php

namespace Aerni\Factory\Factories\Concerns;

use Illuminate\Support\Arr;
use Statamic\Fields\Blueprint;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Aerni\Factory\Factories\Concerns\WithAssets;
use Statamic\Facades\Blueprint as BlueprintFacade;

trait CreatesEntry
{
    use DefinitionHelpers;
    use Publishable;
    use WithSites;
    use WithAssets;

    protected $model = Entry::class;

    public function newModel(array $attributes = []): Entry
    {
        $entry = EntryFacade::make()
            ->collection($this->collectionHandle())
            ->blueprint($this->blueprintHandle());

        if ($slug = Arr::pull($attributes, 'slug')) {
            $entry->slug($slug);
        }

        if ($date = Arr::pull($attributes, 'date')) {
            $entry->date($date);
        }

        $site = Arr::pull($attributes, 'site');

        $site = $entry->sites()->contains($site)
            ? $site
            : $entry->sites()->first();

        $entry->locale($site);

        $entry->published(Arr::pull($attributes, 'published', true));

        return $entry->data($attributes);
    }

    protected function collectionHandle(): string
    {
        return $this->collection
            ?? str(get_class($this))
                ->beforeLast('\\')
                ->afterLast('\\')
                ->lower();
    }

    protected function blueprintHandle(): string
    {
        return $this->blueprint
            ?? str(get_class($this))
                ->afterLast('\\')
                ->remove('Factory')
                ->lower();
    }

    protected function blueprint(): Blueprint
    {
        return BlueprintFacade::find("collections/{$this->collectionHandle()}/{$this->blueprintHandle()}");
    }

    public function modelName(): string
    {
        return parent::modelName().'\\'.ucfirst($this->collectionHandle()).'\\'.ucfirst($this->blueprintHandle());
    }
}
