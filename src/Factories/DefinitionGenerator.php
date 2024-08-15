<?php

namespace Aerni\Factory\Factories;

use Illuminate\Contracts\Support\Arrayable;
use Statamic\Fields\Blueprint;

class DefinitionGenerator implements Arrayable
{
    public function __construct(protected Blueprint $blueprint) {}

    public function toArray(): array
    {
        return $this->mapItems($this->blueprint->fields()->items()->all());
    }

    public function __toString(): string
    {
        return "return {$this->arrayToString($this->toArray())};";
    }

    public function mapItems(array $items): array
    {
        return collect($items)
            ->flatMap($this->mapFieldtypes(...))
            ->all();
    }

    protected function mapFieldtypes(array $item): array
    {
        return match (true) {
            $item['field']['type'] === 'bard' => $this->mapBardAndReplicator($item),
            $item['field']['type'] === 'replicator' => $this->mapBardAndReplicator($item),
            $item['field']['type'] === 'grid' => $this->mapGrid($item),
            $item['field']['type'] === 'table' => $this->mapTable($item),
            default => $this->mapSimple($item),
        };
    }

    protected function mapBardAndReplicator(array $item): array
    {
        $sets = collect($item['field']['sets'])->flatMap(function ($group) {
            return collect($group['sets'])->map(function ($set, $key) {
                return array_merge($this->mapItems($set['fields']), [
                    'type' => $key,
                    'enabled' => true,
                ]);
            });
        })->values()->all();

        return [
            $item['handle'] => $sets,
        ];
    }

    protected function mapGrid(array $item): array
    {
        $fields = collect($item['field']['fields'])
            ->flatMap(fn ($item) => $this->mapItems([$item]))
            ->toArray();

        return [
            $item['handle'] => $fields,
        ];
    }

    protected function mapTable(array $item): array
    {
        $handle = $item['handle'];

        $minRows = $item['field']['factory']['min_rows'];
        $maxRows = $item['field']['factory']['max_rows'];
        $minCells = $item['field']['factory']['min_cells'];
        $maxCells = $item['field']['factory']['max_cells'];
        $rowCount = random_int($minRows, $maxRows);
        $cellCount = random_int($minCells, $maxCells);

        $formatter = $this->formatter($item);

        $table = [
            $handle => [],
        ];

        for ($i = 0; $i < $rowCount; $i++) {
            array_push($table[$handle], [
                'cells' => [],
            ]);
        }

        $table[$handle] = array_map(function ($item) use ($cellCount, $formatter) {
            for ($i = 0; $i < $cellCount; $i++) {
                array_push($item['cells'], $formatter);
            }

            return $item;
        }, $table[$handle]);

        return $table;
    }

    protected function mapSimple(array $item): array
    {
        return [
            $item['handle'] => null,
        ];
    }

    protected function arrayToString($array, $indentLevel = 0): string
    {
        $output = "[\n";
        $indentation = str_repeat('    ', $indentLevel + 1); // 4 spaces per indent level

        foreach ($array as $key => $value) {
            $formattedKey = is_int($key) ? '' : "'$key' => ";

            if (is_array($value)) {
                $formattedValue = $this->arrayToString($value, $indentLevel + 1);
            } else {
                $formattedValue = var_export($value, true);
            }

            $output .= "{$indentation}{$formattedKey}{$formattedValue},\n";
        }

        return $output .= str_repeat('    ', $indentLevel).']';
    }
}
