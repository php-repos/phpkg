<?php

namespace Phpkg\Infra\Strings;

/**
 * Extracts the first N characters from a string.
 *
 * Returns a substring containing the first specified number of characters.
 * If the string is shorter than the requested length, the entire string is returned.
 *
 * @param string $string The input string to extract characters from
 * @param int $length The number of characters to extract from the beginning (default: 20)
 * @return string The substring containing the first N characters
 *
 * @example
 * ```php
 * $text = "This is a very long string that needs truncation";
 * $short = first_characters($text, 10);
 * // Outputs: "This is a "
 * 
 * $short = first_characters($text); // Uses default length of 20
 * // Outputs: "This is a very long "
 * ```
 */
function first_characters(string $string, int $length = 20): string
{
    return substr($string, 0, $length);
}

function hash(string $content, string $algorithm = 'sha256'): string
{
    return \hash($algorithm, $content);
}

function contains(string $haystack, string $needle): bool
{
    return str_contains($haystack, $needle);
}

function starts_with(string $str, string $needle): bool
{
    return str_starts_with($str, $needle);
}

function ends_with(string $str, string $needle): bool
{
    return str_ends_with($str, $needle);
}

/**
 * Truncate a string to a maximum length, adding ellipsis if needed.
 * 
 * @param string $string The string to truncate
 * @param int $max_length Maximum length (including ellipsis if added)
 * @return string Truncated string with ellipsis if truncated
 */
function truncate(string $string, int $max_length): string
{
    if (strlen($string) <= $max_length) {
        return $string;
    }
    
    return substr($string, 0, $max_length - 3) . '...';
}

/**
 * Checks if a string contains glob pattern characters.
 *
 * Determines if a string is a glob pattern by checking for wildcard characters
 * (*, ?, or [) that are used in pattern matching.
 * Note: This function only checks for pattern characters, it does not normalize paths.
 *
 * @param string $string The string to check
 * @return bool True if the string contains glob pattern characters, false otherwise
 *
 * @example
 * ```php
 * $is_pattern = is_pattern('Source/Test*.php');
 * // Returns: true
 *
 * $is_pattern = is_pattern('Source/Service.php');
 * // Returns: false
 * ```
 */
function is_pattern(string $string): bool
{
    return str_contains($string, '*') || str_contains($string, '?') || str_contains($string, '[');
}

function split(string $string, string $delimiter): array
{
    return explode($delimiter, $string);
}

/**
 * Returns the substring starting from the specified position to the end of the string.
 *
 * Uses UTF-8 encoding for multibyte string support.
 *
 * @param string $subject The input string.
 * @param int $position The starting position (zero-based).
 * @return string The substring from the position to the end.
 * @example
 * ```php
 * $result = after('hello', 2); // Returns 'llo'
 * ```
 */
function after(string $subject, int $position): string
{
    return mb_substr(string: $subject, start: $position, encoding: 'UTF-8');
}

/**
 * Returns the substring after the first occurrence of a needle.
 *
 * If the needle is empty or not found, returns the original string.
 *
 * @param string $subject The input string.
 * @param string $needle The substring to search for.
 * @return string The substring after the first occurrence of the needle, or the original string.
 * @example
 * ```php
 * $result = after_first_occurrence('hello world', ' '); // Returns 'world'
 * $result = after_first_occurrence('hello', 'x'); // Returns 'hello'
 * ```
 */
function after_first_occurrence(string $subject, string $needle): string
{
    if ($needle === '' || ($pos = mb_strpos($subject, $needle)) === false) {
        return $subject;
    }

    return after($subject, $pos + mb_strlen($needle));
}

/**
 * Returns the substring from the start of the string up to the specified position.
 *
 * Uses UTF-8 encoding for multibyte string support.
 *
 * @param string $subject The input string.
 * @param int $position The end position (exclusive, zero-based).
 * @return string The substring from the start to the position.
 * @example
 * ```php
 * $result = before('hello', 2); // Returns 'he'
 * ```
 */
function before(string $subject, int $position): string
{
    return mb_substr(string: $subject, start: 0, length: $position, encoding: 'UTF-8');
}

/**
 * Returns the substring before the first occurrence of a needle.
 *
 * If the needle is empty or not found, returns the original string.
 *
 * @param string $subject The input string.
 * @param string $needle The substring to search for.
 * @return string The substring before the first occurrence of the needle, or the original string.
 * @example
 * ```php
 * $result = before_first_occurrence('hello world', ' '); // Returns 'hello'
 * $result = before_first_occurrence('hello', 'x'); // Returns 'hello'
 * ```
 */
function before_first_occurrence(string $subject, string $needle): string
{
    if ($needle === '' || ($pos = mb_strpos($subject, $needle)) === false) {
        return $subject;
    }

    return before($subject, $pos);
}

/**
 * Returns the substring before the last occurrence of a needle.
 *
 * If the needle is empty or not found, returns the original string.
 *
 * @param string $subject The input string.
 * @param string $needle The substring to search for.
 * @return string The substring before the last occurrence of the needle, or the original string.
 * @example
 * ```php
 * $result = before_last_occurrence('hello world hello', ' '); // Returns 'hello world'
 * $result = before_last_occurrence('hello', 'x'); // Returns 'hello'
 * ```
 */
function before_last_occurrence(string $subject, string $needle): string
{
    if ($needle === '' || ($pos = mb_strrpos($subject, $needle)) === false) {
        return $subject;
    }

    return before($subject, $pos);
}

/**
 * Returns the last character of a string.
 *
 * Uses UTF-8 encoding for multibyte string support.
 *
 * @param string $subject The input string.
 * @return string The last character.
 * @example
 * ```php
 * $result = last_character('hello'); // Returns 'o'
 * $result = last_character(''); // Returns ''
 * ```
 */
function last_character(string $subject): string
{
    return mb_substr($subject, -1);
}

/**
 * Removes the last character from a string.
 *
 * Uses UTF-8 encoding for multibyte string support.
 *
 * @param string $subject The input string.
 * @return string The string without the last character.
 * @example
 * ```php
 * $result = remove_last_character('hello'); // Returns 'hell'
 * $result = remove_last_character(''); // Returns ''
 * ```
 */
function remove_last_character(string $subject): string
{
    return mb_substr($subject, 0, mb_strlen($subject) - 1);
}

