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

            return [
                $item['handle'] => $this->formatter($item),
            ];

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
     * Map grid fieldtype to the expected structure of its saved data.
     *
     * @param array $item
     * @return array
     */
    protected function mapGrid(array $item): array
    {
        $handle = $item['handle'];

        $minRows = $item['field']['factory']['min_rows'] ?? $item['field']['min_rows'];
        $maxRows = $item['field']['factory']['max_rows'] ?? $item['field']['max_rows'];
        $rowCount = random_int($minRows, $maxRows);

        $fields = collect($item['field']['fields'])->flatMap(function ($item) {
            return [
                $item['handle'] => $this->formatter($item)
            ];
        })->toArray();

        $grid = [
            $handle => [],
        ];

        for ($i = 0 ; $i < $rowCount; $i++) {
            array_push($grid[$handle], []);
        }

        $grid[$handle] = array_map(function () use ($fields) {
            return $fields;
        }, $grid[$handle]);

        return $grid;
    }

    /**
     * Map table fieldtype to the expected structure of its saved data.
     *
     * @param array $item
     * @return array
     */
    protected function mapTable(array $item): array
    {
        $handle = $item['handle'];

        $rowCount = $item['field']['factory']['rows'];
        $cellCount = $item['field']['factory']['cells'];

        $fakerFormatter = $this->formatter($item);

        $table = [
            $handle => [],
        ];

        for ($i = 0 ; $i < $rowCount; $i++) {
            array_push($table[$handle], [
                'cells' => [],
            ]);
        }

        $table[$handle] = array_map(function ($item) use ($cellCount, $fakerFormatter) {
            for ($i = 0 ; $i < $cellCount; $i++) {
                array_push($item['cells'], $fakerFormatter);
            }

            return $item;
        }, $table[$handle]);

        return $table;
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
     * Check if the item is of a special field type.
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
