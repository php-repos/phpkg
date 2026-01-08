<?php

namespace Phpkg\Infra\Arrays;

use const ARRAY_FILTER_USE_BOTH;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Converts a JSON string to an associative array.
 *
 * Decodes a JSON-encoded string and returns the result as an associative array.
 * This is useful for parsing configuration files, API responses, or any JSON data.
 *
 * @param string $encoded_json The JSON string to decode
 * @return array The decoded JSON as an associative array
 *
 * @example
 * ```php
 * $json = '{"name": "John", "age": 30, "city": "New York"}';
 * $array = json_to_array($json);
 * echo $array['name']; // Outputs: John
 * ```
 */
function json_to_array(string $encoded_json): array
{
    return json_decode($encoded_json, true);
}

/**
 * Adds a value to a nested array at the specified dimensions.
 *
 * Creates nested array structures as needed and adds the value at the specified path.
 * If any intermediate arrays don't exist, they are created automatically.
 *
 * @param array $array The input array to modify
 * @param mixed $value The value to add
 * @param mixed ...$dimension The keys defining the path to the target location
 * @return array The modified array with the new value
 *
 * @example
 * ```php
 * $data = ['users' => ['john' => ['age' => 30]]];
 * $result = add($data, 'admin', 'users', 'jane', 'role');
 * // Result: ['users' => ['john' => ['age' => 30], 'jane' => ['role' => 'admin']]]
 * ```
 */
function add(array $array, mixed $value, mixed ...$dimension): array
{
    $reference = &$array;
    foreach ($dimension as $key) {
        if (!is_array($reference)) {
            $reference = [];
        }
        if (!array_key_exists($key, $reference)) {
            $reference[$key] = [];
        }
        $reference = &$reference[$key];
    }

    $reference = $value;

    return $array;
}

/**
 * Checks if any element in an array satisfies a condition or if the iterable is non-empty.
 *
 * @param array $array The array to check.
 * @param callable|null $condition Optional callback to test each element. Receives value and key as parameters.
 * @return bool True if any element satisfies the condition or if the iterable is non-empty, false otherwise.
 * @example
 * ```php
 * $result = any([1, 2, 3], fn($value) => $value > 2); // Returns true
 * $result = any([]); // Returns false
 * ```
 */
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

/**
 * Finds the first element in an array that satisfies a condition.
 *
 * Returns the first element that passes the test implemented by the provided function.
 * If no condition is provided, returns the first element of the array.
 *
 * @param array $array The array to search
 * @param callable|null $condition Optional test function to apply to each element
 * @return mixed The first matching element or null if none found
 *
 * @example
 * ```php
 * $users = [['name' => 'John', 'age' => 30], ['name' => 'Jane', 'age' => 25]];
 * $first_adult = first($users, fn($user) => $user['age'] >= 18);
 * // Returns: ['name' => 'John', 'age' => 30]
 * 
 * $first_user = first($users); // Returns: ['name' => 'John', 'age' => 30]
 * ```
 */
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

/**
 * Checks if any element in an array satisfies a condition.
 *
 * Returns true if at least one element in the array passes the test implemented by the provided function.
 * Returns false if no elements pass the test.
 *
 * @param array $array The array to test
 * @param callable $condition The test function to apply to each element
 * @return bool True if any element passes the test, false otherwise
 *
 * @example
 * ```php
 * $numbers = [1, 3, 5, 7];
 * $has_even = has($numbers, fn($n) => $n % 2 === 0);
 * // Returns: false (no even numbers)
 * ```
 */
function has(array $array, callable $condition): bool
{
    return any($array, fn($value, $key) => $condition($value, $key));
}

/**
 * Applies a callback function to each element of an array.
 *
 * Creates a new array with the results of calling the provided function for every element in the input array.
 *
 * @param array $array The array to map
 * @param callable $callback The function to apply to each element
 * @return array The new array with mapped values
 *
 * @example
 * ```php
 * $numbers = [1, 2, 3, 4];
 * $squares = map($numbers, fn($n) => $n * $n);
 * // Returns: [1, 4, 9, 16]
 * ```
 */
function map(array $array, callable $callback): array
{
    return array_map($callback, array_values($array), array_keys($array));
}

/**
 * Reduces an array to a single value using a callback function.
 *
 * Applies a function against an accumulator and each element in the array to reduce it to a single value.
 *
 * @param array $array The array to reduce
 * @param callable $callback The function to apply to each element
 * @param mixed $carry The initial value for the accumulator
 * @return mixed The reduced value
 *
 * @example
 * ```php
 * $numbers = [1, 2, 3, 4];
 * $sum = reduce($numbers, fn($carry, $item) => $carry + $item, 0);
 * // Returns: 10 (sum of all numbers)
 * ```
 */
function reduce(array $array, callable $callback, mixed $carry = null): mixed
{
    return array_reduce(
        array_keys($array),
        fn ($carry, $key) => $callback($carry, $array[$key], $key),
        $carry
    );
}

/**
 * Sorts an array using a custom comparison function.
 *
 * Sorts the array in place using the provided comparison function and returns the sorted array.
 *
 * @param array $array The array to sort
 * @param callable $callback The comparison function
 * @return array The sorted array
 *
 * @example
 * ```php
 * $users = [['name' => 'John', 'age' => 30], ['name' => 'Jane', 'age' => 25]];
 * $sorted = sort($users, fn($a, $b) => $a['age'] <=> $b['age']);
 * // Returns: [['name' => 'Jane', 'age' => 25], ['name' => 'John', 'age' => 30]]
 * ```
 */
function sort(array $array, callable $callback): array
{
    usort($array, $callback);
    return $array;
}

function group_by_keys(array $array): array
{
    $groups = [];

    foreach ($array as $sub_array) {
        foreach ($sub_array as $key => $value) {
            if (!isset($groups[$key])) {
                $groups[$key] = [];
            }
            $groups[$key][] = $value;
        }
    }

    return $groups;
}

function group_by(array $array, callable $callback): array
{
    $groups = [];

    foreach ($array as $item) {
        $key = $callback($item);
        if (!array_key_exists($key, $groups)) {
            $groups[$key] = [];
        }
        $groups[$key][] = $item;
    }

    return $groups;
}

function cartesian_product(array ...$array): array
{
    if (empty($array)) {
        return [[]];
    }

    $first = array_shift($array);
    $sub_product = cartesian_product(...$array);

    $result = [];
    foreach ($first as $item) {
        foreach ($sub_product as $product) {
            $result[] = array_merge([$item], $product);
        }
    }

    return $result;
}

function unique(array $array, ?callable $callback = null): array
{
    $callback = $callback ?? fn($a, $b) => $a === $b;
    $result = [];
    foreach ($array as $item) {
        $found = false;
        foreach ($result as $existing) {
            if ($callback($item, $existing)) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $result[] = $item;
        }
    }

    return $result;
}

function sort_keys(array $array): array
{
    ksort($array, SORT_STRING);
    return $array;
}

function sort_by_keys(array $array, callable $callback): array
{
    uksort($array, $callback);
    return $array;
}

function sort_by_keys_desc(array $array, callable $callback): array
{
    return sort_by_keys($array, fn ($a, $b) => $callback($b, $a));
}

function canonical_json_encode(array $array): string
{
    $sorted = sort_keys_recursively($array);
    return json_encode($sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function sort_keys_recursively(array $value): array
{
    $sorted = [];
    foreach (sort_keys($value) as $key => $item) {
        $sorted[$key] = is_iterable($item) ? sort_keys_recursively($item) : $item;
    }

    return $sorted;
}

function filter(array $array, callable $callback): array
{
    return array_filter($array, $callback, ARRAY_FILTER_USE_BOTH);
}
