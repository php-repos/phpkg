<?php

namespace Phpkg\InfrastructureStructure\Strings;

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
