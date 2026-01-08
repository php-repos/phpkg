<?php

namespace PhpRepos\Git\Platform\Arrays;

function any(array $array, ?callable $condition = null): bool
{
    if (is_callable($condition)) {
        if (function_exists('array_any')) {
            return array_any($array, $condition);
        }

        foreach ($array as $key => $value) {
            if ($condition($value, $key)) {
                return true;
            }
        }

        return false;
    }

    return ! empty($array);
}

function first(array $array, ?callable $condition = null): mixed
{
    if (is_callable($condition)) {
        foreach ($array as $key => $value) {
            if ($condition($value, $key)) {
                return $value;
            }
        }

        return null;
    }

    return $array[array_key_first($array)] ?? null;
}

function map(array $array, callable $callback): array
{
    return array_map($callback, array_values($array), array_keys($array));
}