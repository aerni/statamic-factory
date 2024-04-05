<?php

namespace Aerni\Factory;

class Utils
{
    /**
     * Recursively map an array to a callback function.
     *
     * @param  function  $callback
     */
    public static function mapRecursive(array $array, $callback): array
    {
        $output = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $output[$key] = self::mapRecursive($value, $callback);
            } else {
                $output[$key] = $callback($value, $key);
            }
        }

        return $output;
    }
}
