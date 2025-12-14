<?php

namespace Phpkg\Solution\PHPKGs;

use Phpkg\Solution\Versions;
use Phpkg\Solution\Paths;
use Phpkg\Infra\Arrays;
use Phpkg\Infra\Files;
use Phpkg\Infra\Strings;
use function Phpkg\Infra\Logs\log;

function config(array $config): array
{
    log('Normalizing phpkg config', [
        'config' => $config,
    ]);
     
    $result = [];
    $result['map'] = $config['map'] ?? [];
    $result['autoloads'] = $config['autoloads'] ?? [];
    // Normalize excludes to Linux-style paths (forward slashes) for consistency
    $result['excludes'] = isset($config['excludes']) 
        ? array_map(fn (string $exclude) => Paths\normalize($exclude), $config['excludes'])
        : [];
    $result['entry-points'] = $config['entry-points'] ?? [];
    $result['executables'] = $config['executables'] ?? [];
    $result['packages-directory'] = $config['packages-directory'] ?? 'Packages';
    $result['import-file'] = $config['import-file'] ?? 'phpkg.imports.php';
    $result['packages'] = $config['packages'] ?? [];

    foreach ($result['packages'] as $package_url => $version_tag) {
        $result['packages'][$package_url] = Versions\from($package_url, $version_tag);
    }
    $result['aliases'] = $config['aliases'] ?? [];

    return $result;
}

function config_to_array(array $config): array
{
    log('Converting phpkg config to array representation', [
        'config' => $config,
    ]);
    $array_config = [];
    $array_config['map'] = $config['map'];
    $array_config['autoloads'] = $config['autoloads'];
    $array_config['excludes'] = $config['excludes'];
    $array_config['entry-points'] = $config['entry-points'];
    $array_config['executables'] = $config['executables'];
    $array_config['packages-directory'] = $config['packages-directory'];
    $array_config['import-file'] = $config['import-file'];
    $array_config['packages'] = [];
    foreach ($config['packages'] as $package_url => $version) {
        $array_config['packages'][$package_url] = $version->tag;
    }
    $array_config['aliases'] = $config['aliases'];
    return $array_config;
}

function has_entry_point(array $config, string $path): bool
{
    log('Checking if phpkg config has entry point', [
        'config' => $config,
        'path' => $path,
    ]);
    return in_array($path, $config['entry-points'], true);
}

function has_executable(array $config, string $path): bool
{
    log('Checking if phpkg config has executable', [
        'config' => $config,
        'path' => $path,
    ]);
    return in_array($path, $config['executables'], true);
}

function lock_checksum(array $packages): string
{
    log('Calculating lock checksum for packages', [
        'packages' => $packages,
    ]);

    return Strings\hash(Arrays\canonical_json_encode($packages));
}

function verify_lock(string $hash, array $packages): bool
{
    log('Verifying lock checksum for packages', [
        'expected_hash' => $hash,
        'packages' => $packages,
    ]);

    $calculated_hash = lock_checksum($packages);
    return $calculated_hash === $hash;
}

/**
 * Prepends a root path to an exclude pattern.
 * If the exclude is a glob pattern, uses Files\append (no resolution).
 * If the exclude is an exact path, uses Paths\under (with resolution).
 *
 * @param string $root The root path to prepend
 * @param string $exclude The exclude pattern or path
 * @return string The absolute exclude path or pattern
 *
 * @example
 * ```php
 * $pattern = exclude_path('/path/to/project', 'Source/Test*.php');
 * // Returns: '/path/to/project/Source/Test*.php' (not resolved)
 *
 * $path = exclude_path('/path/to/project', 'Source/ExcludedFile.php');
 * // Returns: '/path/to/project/Source/ExcludedFile.php' (resolved)
 * ```
 */
function exclude_path(string $root, string $exclude): string
{
    log('Prepending root to exclude path', [
        'root' => $root,
        'exclude' => $exclude,
    ]);
    
    if (Strings\is_pattern($exclude)) {
        // For glob patterns, use Files\append without resolving (realpath would fail)
        return Files\append($root, $exclude);
    } else {
        // For exact paths, use Paths\under to resolve the path
        return Paths\under($root, $exclude);
    }
}
