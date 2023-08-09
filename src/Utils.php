<?php

namespace Aerni\Factory;

class Utils
{
    /**
     * Recursively map an array to a callback function.
     *
     * @param  function  $callback
     */
    public static function array_map_recursive(array $array, $callback): array
    {
        $output = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $output[$key] = self::array_map_recursive($value, $callback);
            } else {
                $output[$key] = $callback($value, $key);
            }
        }

        return $output;
    }
}
