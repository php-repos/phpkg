<?php

namespace Phpkg\SoftwareSolutions\PHPKGs;

use Phpkg\SoftwareSolutions\Versions;
use Phpkg\InfrastructureStructure\Arrays;
use Phpkg\InfrastructureStructure\Strings;
use function Phpkg\InfrastructureStructure\Logs\log;

function config(array $config): array
{
    log('Normalizing phpkg config', [
        'config' => $config,
    ]);
     
    $result = [];
    $result['map'] = $config['map'] ?? [];
    $result['autoloads'] = $config['autoloads'] ?? [];
    $result['excludes'] = $config['excludes'] ?? [];
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
