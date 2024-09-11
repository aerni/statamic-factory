<?php

namespace Aerni\Factory\Factories\Concerns;

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
}
