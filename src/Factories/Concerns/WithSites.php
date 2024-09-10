<?php

namespace Aerni\Factory\Factories\Concerns;

use Illuminate\Support\Collection;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term;

trait WithSites
{
    public function inSite(string $site): self
    {
        return $this->set('site', $site);
    }

    public function inRandomSite(): self
    {
        return $this->sequence(fn () => ['site' => $this->getSitesFromContentModel()->random()]);
    }

    public function perSite(): self
    {
        $sites = $this->getSitesFromContentModel()->map(fn ($site) => ['site' => $site]);

        return $this->sequence(...$sites)->count(($this->count ?? 1) * $sites->count());
    }

    protected function getSitesFromContentModel(): Collection
    {
        $contentModel = $this->newModel();

        return match (true) {
            $contentModel instanceof Entry => $contentModel->sites(),
            $contentModel instanceof Term => $contentModel->taxonomy()->sites(),
        };
    }
}
