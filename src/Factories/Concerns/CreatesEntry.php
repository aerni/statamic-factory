<?php

namespace Aerni\Factory\Factories\Concerns;

use Illuminate\Support\Arr;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;

trait CreatesEntry
{
    use DefinitionHelpers;
    use Publishable;
    use WithSites;

    protected $model = Entry::class;

    public function newModel(array $attributes = [])
    {
        $entry = EntryFacade::make()
            ->collection($this->collection())
            ->blueprint($this->blueprint());

        if ($slug = Arr::pull($attributes, 'slug')) {
            $entry->slug($slug);
        }

        if (($site = Arr::pull($attributes, 'site')) && $entry->sites()->contains($site)) {
            $entry->locale($site);
        }

        $entry->published(Arr::pull($attributes, 'published', true));

        return $entry->data($attributes);
    }

    protected function collection(): string
    {
        return $this->collection
            ?? str(get_class($this))
                ->beforeLast('\\')
                ->afterLast('\\')
                ->lower();
    }

    protected function blueprint(): string
    {
        return $this->blueprint
            ?? str(get_class($this))
                ->afterLast('\\')
                ->remove('Factory')
                ->lower();
    }

    public function modelName(): string
    {
        return parent::modelName().'\\'.ucfirst($this->collection()).'\\'.ucfirst($this->blueprint());
    }
}
