<?php

namespace Phpkg\Infra\Arrays;

use PhpRepos\Datatype\Arr;

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
 * Converts an iterable to a standard array.
 *
 * Converts any iterable object (arrays, iterators, etc.) to a standard PHP array.
 * This ensures compatibility with array functions that require native arrays.
 *
 * @param iterable $array The iterable to convert
 * @return array The converted array
 *
 * @example
 * ```php
 * $iterator = new ArrayIterator(['a', 'b', 'c']);
 * $array = to_array($iterator);
 * // $array is now ['a', 'b', 'c']
 * ```
 */
function to_array(iterable $array): array
{
    return Arr\to_array($array);
}

/**
 * Adds a value to a nested array at the specified dimensions.
 *
 * Creates nested array structures as needed and adds the value at the specified path.
 * If any intermediate arrays don't exist, they are created automatically.
 *
 * @param iterable $array The input array to modify
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
function add(iterable $array, mixed $value, mixed ...$dimension): array
{
    $array = to_array($array);

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
 * Updates a value in a nested array at the specified dimensions.
 *
 * Updates an existing value in a nested array structure. The path must already exist
 * in the array, otherwise the function may fail or create unexpected results.
 *
 * @param iterable $array The input array to modify
 * @param mixed $value The new value to set
 * @param mixed ...$dimension The keys defining the path to the target location
 * @return array The modified array with the updated value
 *
 * @example
 * ```php
 * $data = ['users' => ['john' => ['age' => 30]]];
 * $result = update($data, 31, 'users', 'john', 'age');
 * // Result: ['users' => ['john' => ['age' => 31]]]
 * ```
 */
function update(iterable $array, mixed $value, mixed ...$dimension): array
{
    $array = to_array($array);
    $ref = &$array;

    foreach ($dimension as $key) {
        $ref = &$ref[$key];
    }

    $ref = $value;

    return $array;
}

/**
 * Finds the first element in an array that satisfies a condition.
 *
 * Returns the first element that passes the test implemented by the provided function.
 * If no condition is provided, returns the first element of the array.
 *
 * @param iterable $array The array to search
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
function first(iterable $array, ?callable $condition = null): mixed
{
    return Arr\first($array, $condition);
}

/**
 * Checks if any element in an array satisfies a condition.
 *
 * Returns true if at least one element in the array passes the test implemented by the provided function.
 * Returns false if no elements pass the test.
 *
 * @param iterable $array The array to test
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
function has(iterable $array, callable $condition): bool
{
    return Arr\has($array, $condition);
}

/**
 * Applies a callback function to each element of an array.
 *
 * Creates a new array with the results of calling the provided function for every element in the input array.
 *
 * @param iterable $array The array to map
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
function map(iterable $array, callable $callback): array
{
    return Arr\map($array, $callback);
}

/**
 * Reduces an array to a single value using a callback function.
 *
 * Applies a function against an accumulator and each element in the array to reduce it to a single value.
 *
 * @param iterable $array The array to reduce
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
function reduce(iterable $array, callable $callback, mixed $carry = null): mixed
{
    return Arr\reduce($array, $callback, $carry);
}

/**
 * Sorts an array using a custom comparison function.
 *
 * Sorts the array in place using the provided comparison function and returns the sorted array.
 *
 * @param iterable $array The array to sort
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
function sort(iterable $array, callable $callback): array
{
    $array = to_array($array);
    usort($array, $callback);
    return $array;
}

function group_by_keys(iterable $array): array
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

function group_by(iterable $array, callable $callback): array
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

function cartesian_product(iterable ...$array): array
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

function unique(iterable $array, ?callable $callback = null): array
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

function sort_keys(iterable $array): array
{
    $array = to_array($array);
    ksort($array, SORT_STRING);
    return $array;
}

function sort_by_keys(iterable $array, callable $callback): array
{
    uksort($array, $callback);
    return $array;
}

function sort_by_keys_desc(iterable $array, callable $callback): array
{
    return sort_by_keys($array, fn ($a, $b) => $callback($b, $a));
}

function canonical_json_encode(iterable $array): string
{
    $sorted = sort_keys_recursively($array);
    return json_encode($sorted, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
}

function sort_keys_recursively(iterable $value): mixed
{
    $sorted = [];
    foreach (sort_keys($value) as $key => $item) {
        $sorted[$key] = is_iterable($item) ? sort_keys_recursively($item) : $item;
    }

    return $sorted;
}
