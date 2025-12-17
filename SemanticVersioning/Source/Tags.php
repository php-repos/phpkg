<?php

namespace PhpRepos\SemanticVersioning\Tags;

use function PhpRepos\Datatype\Arr\reduce;
use function PhpRepos\Datatype\Str\before_first_occurrence;

/**
 * Compare two version strings.
 *
 * This function normalizes version strings by removing v/V prefixes and then compares
 * them using PHP's built-in version_compare function. It handles semantic versioning
 * rules including pre-release and build metadata.
 *
 * @param string $version1 The first version string to compare.
 * @param string $version2 The second version string to compare.
 * @return int Returns -1 if version1 is less than version2, 0 if equal, 1 if greater.
 * 
 * @example
 * ```php
 * compare('1.0.0', '2.0.0');           // Returns -1 (1.0.0 < 2.0.0)
 * compare('2.0.0', '1.0.0');           // Returns 1  (2.0.0 > 1.0.0)
 * compare('1.0.0', '1.0.0');           // Returns 0  (1.0.0 == 1.0.0)
 * compare('v1.0.0', '1.0.0');          // Returns 0  (v1.0.0 == 1.0.0)
 * compare('1.0.0-alpha', '1.0.0');     // Returns -1 (pre-release < release)
 * compare('1.0.0', '1.0.0+build.1');  // Returns 0  (build metadata ignored)
 * ```
 */
function compare(string $version1, string $version2): int
{
    $version1_string = trim(ltrim(strtolower($version1), 'v'));
    $version2_string = trim(ltrim(strtolower($version2), 'v'));

    return version_compare($version1_string, $version2_string);
}

/**
 * Check if there is a major version change between two version strings.
 *
 * This function extracts the major version part from both versions and compares them.
 * A major version change occurs when the major version numbers are different.
 *
 * @param string $version1 The first version string.
 * @param string $version2 The second version string.
 * @return bool True if there is a major version change, false otherwise.
 * 
 * @example
 * ```php
 * has_major_change('1.0.0', '2.0.0');           // Returns true  (1 != 2)
 * has_major_change('1.0.0', '1.1.0');           // Returns false (1 == 1)
 * has_major_change('1.0.0', '1.0.1');           // Returns false (1 == 1)
 * has_major_change('v1.0.0', 'V2.0.0');         // Returns true  (1 != 2)
 * has_major_change('10.0.0', '11.0.0');         // Returns true  (10 != 11)
 * ```
 */
function has_major_change(string $version1, string $version2): bool
{
    return major($version1) !== major($version2);
}

/**
 * Extract the major version part from a version string.
 *
 * This function extracts the major version number by finding the first part before
 * any separator (+, -, _, .) and removing v/V prefixes. It handles complex version
 * strings with pre-release and build metadata.
 *
 * @param string $version The version string to extract from.
 * @return string The major version part as a string.
 * 
 * @example
 * ```php
 * major('1.0.0');                    // Returns '1'
 * major('2.1.3');                    // Returns '2'
 * major('10.20.30');                 // Returns '10'
 * major('v1.0.0');                   // Returns '1'
 * major('V2.1.3');                   // Returns '2'
 * major('1.0.0-alpha');              // Returns '1'
 * major('2.1.3-beta.1');            // Returns '2'
 * major('1.0.0+build.1');           // Returns '1'
 * major('3.0.0+20130313144700');    // Returns '3'
 * ```
 */
function major(string $version): string
{
    $version_string = ltrim($version, 'vV');

    return reduce(
        ['+', '-', '_', '.'],
        function (string $major, string $separator) use ($version_string) {
            $possible_major = before_first_occurrence($version_string, $separator);
            return strlen($possible_major) <= strlen($major) ? $possible_major : $major;
        },
        $version_string,
    );
}

/**
 * Check if a string represents a valid semantic version format.
 *
 * This function validates semantic version strings according to SemVer 2.0.0 specification.
 * It checks for proper format: major.minor.patch[-pre-release][+build-metadata].
 * The function handles v/V prefixes and validates that version parts are numeric.
 *
 * @param string $version The version string to validate.
 * @return bool True if valid semantic version, false otherwise.
 * 
 * @example
 * ```php
 * // Valid versions
 * is_valid_semantic('1.0.0');                    // Returns true
 * is_valid_semantic('2.1.3');                    // Returns true
 * is_valid_semantic('v1.0.0');                   // Returns true
 * is_valid_semantic('V2.1.3');                   // Returns true
 * is_valid_semantic('1.0.0-alpha');              // Returns true
 * is_valid_semantic('2.1.3-beta.1');            // Returns true
 * is_valid_semantic('1.0.0+build.1');           // Returns true
 * is_valid_semantic('1.0.0-alpha+build.1');     // Returns true
 * 
 * // Invalid versions
 * is_valid_semantic('');                         // Returns false
 * is_valid_semantic('invalid');                  // Returns false
 * is_valid_semantic('1.0');                      // Returns false (needs 3 parts)
 * is_valid_semantic('1.0.0.');                   // Returns false (trailing dot)
 * is_valid_semantic('1.0.0-');                   // Returns false (empty pre-release)
 * is_valid_semantic('1.0.0+');                   // Returns false (empty build metadata)
 * ```
 */
function is_valid_semantic(string $version): bool
{
    // Normalize the version by removing v/V prefix
    $normalized = trim(ltrim($version, 'vV'));
    
    // Must not be empty
    if (empty($normalized)) {
        return false;
    }
    
    // Find the core version part (before any - or +)
    $core_version = $normalized;
    $dash_pos = strpos($normalized, '-');
    $plus_pos = strpos($normalized, '+');
    
    if ($dash_pos !== false || $plus_pos !== false) {
        // Get the position of the first separator
        $first_separator = false;
        if ($dash_pos !== false && $plus_pos !== false) {
            $first_separator = min($dash_pos, $plus_pos);
        } elseif ($dash_pos !== false) {
            $first_separator = $dash_pos;
        } elseif ($plus_pos !== false) {
            $first_separator = $plus_pos;
        }
        
        if ($first_separator !== false) {
            $core_version = substr($normalized, 0, $first_separator);
            
            // Check that there's actual content after the separator
            $after_separator = substr($normalized, $first_separator + 1);
            if (empty($after_separator)) {
                return false;
            }
            
            // Build metadata should not start with a dot
            if ($normalized[$first_separator] === '+' && $after_separator[0] === '.') {
                return false;
            }
        }
    }
    
    // Core version must have exactly 3 parts (major.minor.patch) according to SemVer
    $version_parts = explode('.', $core_version);
    if (count($version_parts) !== 3) {
        return false;
    }
    
    foreach ($version_parts as $part) {
        if ($part === '' || !ctype_digit($part)) {
            return false;
        }
    }
    
    // Pre-release and build metadata can contain any characters
    // But they cannot be empty (e.g., "1.0.0-" or "1.0.0+" are invalid)
    // Also check for trailing dots
    if (str_ends_with($normalized, '-') || str_ends_with($normalized, '+') || str_ends_with($normalized, '.')) {
        return false;
    }
    
    return true;
}

/**
 * Check if a version string represents a stable version.
 *
 * A stable version follows the exact x.y.z pattern without pre-release identifiers
 * or build metadata. It must have exactly 3 numeric parts separated by dots.
 *
 * @param string $version The version string to check.
 * @return bool True if the version is stable (follows x.y.z pattern), false otherwise.
 * 
 * @example
 * ```php
 * // Stable versions
 * is_stable('1.0.0');                    // Returns true
 * is_stable('2.1.3');                    // Returns true
 * is_stable('10.20.30');                 // Returns true
 * is_stable('v1.0.0');                   // Returns true
 * is_stable('V2.1.3');                   // Returns true
 * 
 * // Unstable versions
 * is_stable('1.0.0-alpha');              // Returns false (has pre-release)
 * is_stable('2.1.3-beta.1');            // Returns false (has pre-release)
 * is_stable('1.0.0+build.1');           // Returns false (has build metadata)
 * is_stable('1.0.0-alpha+build.1');     // Returns false (has both)
 * is_stable('1.0');                      // Returns false (needs 3 parts)
 * is_stable('1.0.0.0');                  // Returns false (too many parts)
 * ```
 */
function is_stable(string $version): bool
{
    $normalized = ltrim($version, 'vV');
    
    // Stable version must not contain pre-release or build metadata
    if (str_contains($normalized, '-') || str_contains($normalized, '+')) {
        return false;
    }
    
    // Must have exactly 3 parts separated by dots (major.minor.patch)
    $parts = explode('.', $normalized);
    if (count($parts) !== 3) {
        return false;
    }
    
    // Each part must be a number
    foreach ($parts as $part) {
        if (!ctype_digit($part)) {
            return false;
        }
    }
    
    return true;
}
