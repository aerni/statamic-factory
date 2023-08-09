<?php

namespace Aerni\Factory;

use Illuminate\Support\Collection;

class Mapper
{
    /**
     * Map the items.
     *
     * @param  Collection  $items
     */
    public function mapItems(array $items): array
    {
        return collect($items)->flatMap(function ($item) {
            if ($this->isSpecialFieldtype($item)) {
                return $this->handleSpecialFieldtype($item);
            }

            return $this->mapSimple($item);
        })->toArray();
    }

    /**
     * Handle special fieldtype.
     */
    protected function handleSpecialFieldtype(array $item): array
    {
        if ($item['field']['type'] === 'grid') {
            return $this->mapGrid($item);
        }

        if ($item['field']['type'] === 'bard') {
            return $this->mapBardAndReplicator($item);
        }

        if ($item['field']['type'] === 'replicator') {
            return $this->mapBardAndReplicator($item);
        }

        if ($item['field']['type'] === 'table') {
            return $this->mapTable($item);
        }
    }

    /**
     * Map bard and replicator fieldtype to its expected data structure.
     */
    protected function mapBardAndReplicator(array $item): array
    {
        $handle = $item['handle'];
        $sets = $item['field']['sets'];

        $setCount = collect($sets)->map(function ($item, $key) {
            $minSets = $item['factory']['min_sets'];
            $maxSets = $item['factory']['max_sets'];

            return random_int($minSets, $maxSets);
        })->toArray();

        $sets = collect($item['field']['sets'])->map(function ($set, $key) {
            $fields = collect($set['fields'])->flatMap(function ($item) {
                return $this->mapItems([$item]);
            });

            return $fields->merge([
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

        $fields = collect($item['field']['fields'])->flatMap(function ($item) {
            return $this->mapItems([$item]);
        })->toArray();

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
        if (is_array($item['field']['factory'])) {
            return $item['field']['factory']['formatter'];
        }

        return $item['field']['factory'];
    }

    /**
     * Check if the item is a fieldtype that needs special handling.
     */
    protected function isSpecialFieldtype(array $item): bool
    {
        $specialFieldtypes = ['bard', 'grid', 'replicator', 'table'];

        if (in_array($item['field']['type'], $specialFieldtypes)) {
            return true;
        }

        return false;
    }
}
