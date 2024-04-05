<?php

namespace Aerni\Factory;

class Mapper
{
    /**
     * Map the items.
     */
    public function mapItems(array $items): array
    {
        return collect($items)
            ->flatMap(fn ($item) => $this->mapFieldtypes($item))
            ->filter(fn ($value, $key) => $this->isFakerFormatter($value, $key))
            ->toArray();
    }

    /**
     * Map the data according to its fieldtype.
     */
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

    /**
     * Map bard and replicator fieldtype to its expected data structure.
     */
    protected function mapBardAndReplicator(array $item): array
    {
        $handle = $item['handle'];
        $sets = $item['field']['sets'];

        $setCount = collect($sets)
            ->map(fn ($item) => random_int($item['factory']['min_sets'], $item['factory']['max_sets']))
            ->toArray();

        $sets = collect($item['field']['sets'])->map(function ($set, $key) {
            return collect($set['fields'])
                ->flatMap(fn ($item) => $this->mapItems([$item]))
                ->merge([
                    'type' => $key,
                    'enabled' => true,
                ]);
        })->toArray();

        $items = collect($sets)->flatMap(function ($set, $key) use ($setCount, $sets) {
            $items = [];

            for ($i = 0; $i < $setCount[$key]; $i++) {
                array_push($items, $sets[$key]);
            }

            return $items;
        })->toArray();

        return [
            $handle => $items,
        ];
    }

    /**
     * Map grid fieldtype to its expected data structure.
     */
    protected function mapGrid(array $item): array
    {
        $handle = $item['handle'];

        $minRows = $item['field']['factory']['min_rows'];
        $maxRows = $item['field']['factory']['max_rows'];
        $rowCount = random_int($minRows, $maxRows);

        $fields = collect($item['field']['fields'])
            ->flatMap(fn ($item) => $this->mapItems([$item]))
            ->toArray();

        $grid = [
            $handle => [],
        ];

        for ($i = 0; $i < $rowCount; $i++) {
            array_push($grid[$handle], $fields);
        }

        return $grid;
    }

    /**
     * Map table fieldtype to its expected data structure.
     */
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

    /**
     * Map a simple fieldtype to its expected data structure.
     */
    protected function mapSimple(array $item): array
    {
        return [
            $item['handle'] => $this->formatter($item),
        ];
    }

    /**
     * Get the faker formatter from an item.
     */
    protected function formatter(array $item): string
    {
        return is_array($item['field']['factory'])
            ? $item['field']['factory']['formatter']
            : $item['field']['factory'];
    }

    /**
     * Check if the passed value is a faker formatter.
     */
    protected function isFakerFormatter(mixed $value, string $key): bool
    {
        return match (true) {
            is_array($value) => false,
            $key === 'type' => false,
            $key === 'enabled' => false,
            default => true,
        };
    }
}
