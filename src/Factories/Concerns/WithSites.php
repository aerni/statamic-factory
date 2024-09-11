<?php

namespace Aerni\Factory\Factories\Concerns;

use Faker\Generator;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Facades\Site;

trait WithSites
{
    public function inSite(string $site): self
    {
        return $this->set('site', $site);
    }

    public function inRandomSite(): self
    {
        return $this->state(fn () => [
            'site' => $this->getSitesFromContentModel()->random(),
            'isRandomSite' => true,
        ]);
    }

    public function inEachSite(?int $count = null): self
    {
        $sites = $this->getSitesFromContentModel()->map(fn ($site) => ['site' => $site]);

        return $this->sequence(...$sites)->count(($count ?? $this->count ?? 1) * $sites->count());
    }

    protected function getSitesFromContentModel(): Collection
    {
        return once(function () {
            $contentModel = $this->newModel();

            return match (true) {
                $contentModel instanceof Entry => $contentModel->sites(),
                $contentModel instanceof Term => $contentModel->taxonomy()->sites(),
                default => Site::all()->map->handle(),
            };
        });
    }

    protected function evaluateSiteStates(Collection $states): Collection
    {
        $evaluatedSiteStates = $states
            ->map(fn ($state) => (clone $state)()) /* Clone the closure so that we don't run into issues when evaluating the same closure later. Needed for sequences to work correctly. */
            ->filter(fn ($state) => isset($state['site']))
            ->map(fn ($state, $index) => array_merge(['index' => $index], $state));

        if ($evaluatedSiteStates->isEmpty()) {
            return $states;
        }

        $siteState = $evaluatedSiteStates->last();

        $site = $this->getSitesFromContentModel()->flip()->has($siteState['site'])
            ? Site::get($siteState['site'])
            : Site::get($this->getSitesFromContentModel()->first());

        $this->faker = Container::getInstance()->makeWith(Generator::class, ['locale' => $site->locale()]);

        $siteState = ! isset($siteState['isRandomSite'])
            ? $states->get($siteState['index'])
            : fn () => ['site' => $site->handle()]; /* Explicitly set the evaluated random site so that we don't get a new random site later. */

        return $states->diffKeys($evaluatedSiteStates)
            ->push($siteState)
            ->values();
    }
}
