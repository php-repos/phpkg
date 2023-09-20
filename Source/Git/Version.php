<?php

namespace Phpkg\Git\Version;

function is_semantic(string $version): bool
{
    // Define a regular expression pattern for SemVer with optional "v" prefix
    $pattern = '/^(?:v)?(\d+)\.(\d+)\.(\d+)$/';

    // Use preg_match to check if the version matches the pattern
    return preg_match($pattern, $version) === 1;
}

function compare(string $version1, string $version2): int
{
    // Remove the "v" prefix if it exists
    $version1 = ltrim($version1, 'v');
    $version2 = ltrim($version2, 'v');

    [$major1, $minor1, $patch1] = explode('.', $version1);
    [$major2, $minor2, $patch2] = explode('.', $version2);

    $difference = strcmp($major1, $major2);

    if ($difference === 0) {
        $difference = strcmp($minor1, $minor2);

        if ($difference === 0) {
            $difference = strcmp($patch1, $patch2);
        }
    }

    return $difference;
}

function has_major_change(string $version1, string $version2): bool
{
    // Remove the "v" prefix if it exists
    $version1 = ltrim($version1, 'v');
    $version2 = ltrim($version2, 'v');

    return explode('.', $version1)[0] !== explode('.', $version2)[0];
}
