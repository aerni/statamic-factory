<?php

namespace Aerni\Factory;

use Illuminate\Support\Collection as SupportCollection;

class Mapper
{
    /**
     * Map the items.
     *
     * @param SupportCollection $items
     * @return SupportCollection
     */
    public function mapItems(SupportCollection $items): SupportCollection
    {
        return $items->flatMap(function ($item) {
            if ($this->isSpecialFieldtype($item)) {
                return $this->handleSpecialFieldtype($item);
            }

            return $this->mapSimple($item);
        });
    }

    /**
     * Handle special fieldtype.
     *
     * @param array $item
     * @return array
     */
    protected function handleSpecialFieldtype(array $item): array
    {
        if ($item['field']['type'] === 'grid') {
            return $this->mapGrid($item);
        }

        if ($item['field']['type'] === 'table') {
            return $this->mapTable($item);
        }
    }

    /**
     * Map grid fieldtype to its expected data structure.
     *
     * @param array $item
     * @return array
     */
    protected function mapGrid(array $item): array
    {
        $handle = $item['handle'];

        $minRows = $item['field']['factory']['min_rows'];
        $maxRows = $item['field']['factory']['max_rows'];
        $rowCount = random_int($minRows, $maxRows);

        $fields = collect($item['field']['fields'])->flatMap(function ($item) {
            return $this->mapItems(collect([$item]));
        })->toArray();

        $grid = [
            $handle => [],
        ];

        for ($i = 0 ; $i < $rowCount; $i++) {
            array_push($grid[$handle], $fields);
        }

        return $grid;
    }

    /**
     * Map table fieldtype to its expected data structure.
     *
     * @param array $item
     * @return array
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

        for ($i = 0 ; $i < $rowCount; $i++) {
            array_push($table[$handle], [
                'cells' => [],
            ]);
        }

        $table[$handle] = array_map(function ($item) use ($cellCount, $formatter) {
            for ($i = 0 ; $i < $cellCount; $i++) {
                array_push($item['cells'], $formatter);
            }

            return $item;
        }, $table[$handle]);

        return $table;
    }

    /**
     * Map a simple fieldtype to its expected data structure.
     *
     * @param array $item
     * @return array
     */
    protected function mapSimple(array $item): array
    {
        return [
            $item['handle'] => $this->formatter($item),
        ];
    }

    /**
     * Get the faker formatter from an item.
     *
     * @param array $item
     * @return string
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
     *
     * @param array $item
     * @return bool
     */
    protected function isSpecialFieldtype(array $item): bool
    {
        $specialFieldtypes = ['grid', 'table'];

        if (in_array($item['field']['type'], $specialFieldtypes)) {
            return true;
        }

        return false;
    }
}
