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

    public static function arrayToString($array, $indentLevel = 0): string
    {
        $output = "[\n";
        $indentation = str_repeat('    ', $indentLevel + 1); // 4 spaces per indent level

        foreach ($array as $key => $value) {
            $formattedKey = is_int($key) ? '' : "'$key' => ";

            if (is_array($value)) {
                $formattedValue = self::arrayToString($value, $indentLevel + 1);
            } else {
                $formattedValue = var_export($value, true);
            }

            $output .= "{$indentation}{$formattedKey}{$formattedValue},\n";
        }

        return $output .= str_repeat('    ', $indentLevel).']';
    }
}
