<?php

namespace Aerni\Factory\Factories\Concerns;

use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Collection;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term;

trait WithSites
{
    public function inSite(string $site): self
    {
        return $this->newInstance(['site' => $site]);
    }

    public function inRandomSite(): self
    {
        return $this->inSite('inRandomSite');
    }

    public function perSite(): self
    {
        return $this->inSite('perSite')->count($this->getSitesFromContentModel()->count() * ($this->count ?? 1));
    }

    protected function evaluateSite(): self
    {
        $evaluatedSite = match (true) {
            $this->getSitesFromContentModel()->contains($this->site) => $this->site,
            $this->site === 'inRandomSite' => $this->getSitesFromContentModel()->random(),
            $this->site === 'perSite' => once(fn () => new Sequence(...$this->getSitesFromContentModel()))(), /* We are using once() so that the Sequence works correctly and isn't created afresh every time this method is called. */
            default => $this->getDefaultSiteFromContentModel(),
        };

        return $this->inSite($evaluatedSite);
    }

    protected function getSitesFromContentModel(): Collection
    {
        return once(function () {
            $contentModel = $this->newModel();

            return match (true) {
                $contentModel instanceof Entry => $contentModel->sites(),
                $contentModel instanceof Term => $contentModel->taxonomy()->sites(),
            };
        });
    }

    protected function getDefaultSiteFromContentModel(): string
    {
        return $this->getSitesFromContentModel()->first();
    }
}
