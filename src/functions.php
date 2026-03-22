<?php

declare(strict_types=1);

namespace App;

/**
 * Flattens a multidimensional array into a single level array.
 *
 * @template T
 *
 * @param array<array-key, array<array-key, T>|T> $array The array to flatten
 *
 * @return list<T> The flattened array
 */
function array_flatten(array $array): array
{
    $result = [];
    array_walk_recursive($array, function ($item) use (&$result) {
        $result[] = $item;
    });

    return $result;
}
