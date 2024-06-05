<?php

namespace Phpkg\Git\Version;

use function PhpRepos\Datatype\Arr\reduce;
use function PhpRepos\Datatype\Str\before_first_occurrence;

function compare(string $version1, string $version2): int
{
    $version1 = trim(ltrim($version1, 'vV'));
    $version2 = trim(ltrim($version2, 'vV'));

    return version_compare($version1, $version2);
}

function has_major_change(string $version1, string $version2): bool
{
    return major($version1) !== major($version2);
}

function major(string $version): string
{
    $version = ltrim($version, 'vV');

    return reduce(
        ['+', '-', '_', '.'],
        function (string $major, string $separator) use ($version) {
            $possible_major = before_first_occurrence($version, $separator);
            return strlen($possible_major) <= strlen($major) ? $possible_major : $major;
        },
        $version,
    );
}

function is_stable(string $version): bool
{
    return preg_match('/^\d+\.\d+\.\d+$/', ltrim($version, 'vV')) === 1;
}
