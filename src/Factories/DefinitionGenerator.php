<?php

namespace Aerni\Factory\Factories;

use Aerni\Factory\Support\Utils;
use Illuminate\Contracts\Support\Arrayable;
use Statamic\Fields\Blueprint;

class DefinitionGenerator implements Arrayable
{
    public function __construct(protected Blueprint $blueprint) {}

    public function toArray(): array
    {
        return $this->processBlueprint();
    }

    public function __toString(): string
    {
        return Utils::arrayToString($this->toArray());
    }

    protected function processBlueprint(): array
    {
        return $this->blueprint->fields()->all()
            ->reject(fn ($field) => $field->visibility() === 'computed')
            ->map(fn ($field) => ['handle' => $field->handle(), 'field' => $field->config()])
            ->flatMap($this->processFields(...))
            ->all();
    }

    protected function processNestedFields(array $fields): array
    {
        return collect($fields)
            ->flatMap($this->processFields(...))
            ->all();
    }

    protected function processFields(array $config): array
    {
        return match (true) {
            $config['field']['type'] === 'bard' => $this->processBardAndReplicator($config),
            $config['field']['type'] === 'replicator' => $this->processBardAndReplicator($config),
            $config['field']['type'] === 'grid' => $this->processGrid($config),
            default => [$config['handle'] => null]
        };
    }

    protected function processBardAndReplicator(array $config): array
    {
        $fields = collect($config['field']['sets'])->flatMap(function ($group) {
            return collect($group['sets'])->map(function ($set, $key) {
                return array_merge($this->processNestedFields($set['fields']), [
                    'type' => $key,
                    'enabled' => true,
                ]);
            });
        })->values()->all();

        return [$config['handle'] => $fields];
    }

    protected function processGrid(array $config): array
    {
        $fields = collect($config['field']['fields'])
            ->flatMap(fn ($config) => $this->processNestedFields([$config]))
            ->toArray();

        return [$config['handle'] => $fields];
    }
}
