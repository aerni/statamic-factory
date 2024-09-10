<?php

namespace Aerni\Factory\Factories\Concerns;

use Illuminate\Support\Arr;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Facades\Term as TermFacade;

trait CreatesTerm
{
    use DefinitionHelpers;
    use WithSites;

    protected $model = Term::class;

    public function newModel(array $attributes = [])
    {
        $term = TermFacade::make()
            ->taxonomy($this->taxonomy())
            ->blueprint($this->blueprint());

        $published = Arr::pull($attributes, 'published', true);
        $slug = Arr::pull($attributes, 'slug');
        $site = Arr::pull($attributes, 'site');

        /**
         * If the term is *not* being created in the default site, we'll copy all the
         * appropriate values into the default localization since it needs to exist.
         */
        if ($site !== $term->defaultLocale()) {
            $term
                ->inDefaultLocale()
                ->published($published)
                ->slug($slug)
                ->data($attributes);
        }

        /* Ensure we only create localizations for sites that are configured on the taxonomy. */
        if ($term->taxonomy()->sites()->contains($site)) {
            $term
                ->in($site)
                ->published($published)
                ->slug($slug)
                ->data($attributes);
        }

        return $term;
    }

    protected function taxonomy(): string
    {
        return $this->taxonomy
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
