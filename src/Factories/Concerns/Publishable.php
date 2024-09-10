<?php

namespace Aerni\Factory\Factories\Concerns;

trait Publishable
{
    public function unpublished(): self
    {
        return $this->set('published', false);
    }
}
