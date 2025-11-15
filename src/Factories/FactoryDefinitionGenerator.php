<?php

namespace Aerni\Factory\Factories;

use Aerni\Factory\Support\Utils;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;

class FactoryDefinitionGenerator implements Arrayable
{
    public function __construct(protected Blueprint $blueprint) {}

    public function toArray(): array
    {
        return $this->process();
    }

    public function __toString(): string
    {
        return Utils::arrayToString($this->toArray(), 2);
    }

    protected function defaults(): array
    {
        return [];
    }

    protected function process(): array
    {
        return array_merge($this->processBlueprint(), $this->defaults());
    }

    protected function processBlueprint(): array
    {
        return $this->processFields($this->blueprint->fields()->all());
    }

    protected function processFields(Collection $fields): array
    {
        return $fields->map(fn (Field $field) => match ($field->type()) {
            'bard' => $this->processBardAndReplicator($field),
            'replicator' => $this->processBardAndReplicator($field),
            'grid' => $this->processGrid($field),
            default => null,
        })->all();
    }

    protected function processBardAndReplicator(Field $field): array
    {
        return collect($field->toArray()['sets'])
            ->flatMap(function ($setGroup) {
                return collect($setGroup['sets'])->map(function ($set, $type) {
                    return array_merge(
                        $this->processFields((new Fields($set['fields']))->all()),
                        ['type' => $type, 'enabled' => true]
                    );
                });
            })->values()->toArray();
    }

    protected function processGrid(Field $field): array
    {
        $fields = (new Fields($field->toArray()['fields']))
            ->all()
            ->pipe($this->processFields(...));

        return [$fields];
    }
}
