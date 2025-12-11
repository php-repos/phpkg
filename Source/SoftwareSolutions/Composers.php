<?php

namespace Phpkg\SoftwareSolutions\Composers;

use Phpkg\SoftwareSolutions\Data\Repository;
use Phpkg\SoftwareSolutions\Exceptions\CanNotDetectComposerPackageVersionException;
use Phpkg\SoftwareSolutions\Commits;
use Phpkg\SoftwareSolutions\Dependencies;
use Phpkg\SoftwareSolutions\Repositories;
use Phpkg\SoftwareSolutions\Paths;
use Phpkg\InfrastructureStructure\Strings;
use function PhpRepos\Datatype\Str\last_character;
use function PhpRepos\Datatype\Str\remove_last_character;
use function PhpRepos\Git\Hosts\tags;
use function PhpRepos\SemanticVersioning\Tags\compare;
use function PhpRepos\SemanticVersioning\Tags\has_major_change;
use function PhpRepos\SemanticVersioning\Tags\is_stable;
use function Phpkg\InfrastructureStructure\Logs\debug;
use function Phpkg\InfrastructureStructure\Logs\log;

function detect_packages(array $composer_lock, string $vendor, array $credentials): array
{
    log('Detecting packages from composer.lock', [
        'vendor' => $vendor,
    ]);

    $packages = [];

    foreach ($composer_lock['packages'] as $package) {
        if (! Strings\contains($package['name'], '/')) {
            continue;
        }

        if ($package['name'] === 'composer/composer') {
            continue;
        }

        if (is_dev_package($package['version'])) {
            continue;
        }
        $config = config_from_local($package, $composer_lock);

        $commit = Commits\prepare($package['source']['url'], $package['version'], $package['source']['reference'], $credentials);
        $package_root = Paths\package_root($vendor, $commit->version->repository->owner, $commit->version->repository->repo);
        $packages[] = Dependencies\setup($commit, $config, $package_root);
    }

    return $packages;
}

function is_dev_package(string $version_pattern): bool
{
    debug('Checking if package is dev', [
        'version_pattern' => $version_pattern,
    ]);
    return Strings\contains($version_pattern, 'dev');
}

function config_from_local(array $composer_config, array $composer_lock): array
{
    debug('Creating config from local composer.json and composer.lock', [
        'composer_config' => $composer_config,
    ]);
    $config = [
        'map' => [],
        'autoloads' => [],
        'excludes' => [],
        'entry_points' => [],
        'executables' => [],
        'packages_directory' => 'vendor',
        'import_file' => 'vendor/autoload.php',
        'packages' => [],
        'aliases' => [],
    ];

    if (isset($composer_config['require'])) {
        foreach ($composer_config['require'] as $packagist_name => $version_pattern) {
            if (! Strings\contains($packagist_name, '/')) {
                continue;
            }

            if ($packagist_name === 'composer/composer') {
                continue;
            }

            if (is_dev_package($version_pattern)) {
                continue;
            }

            $matching_package = null;
            foreach ($composer_lock['packages'] as $package) {
                if ($package['name'] !== $packagist_name) {
                    continue;
                }
                $matching_package = $package;
                break;
            }

            if ($matching_package) {
                $git_url = $matching_package['source']['url'];
                $version = $matching_package['version'];
                $config['packages'][$git_url] = $version;
            }
        }
    }

    if (isset($composer_config['autoload']['psr-4'])) {
        foreach ($composer_config['autoload']['psr-4'] as $namespace => $path) {
            if (! is_string($path)) {
                continue;
            }

            $namespace = last_character($namespace) === '\\' ? remove_last_character($namespace) : $namespace;
            $path = last_character($path) === '/' ? remove_last_character($path) : $path;

            $config['map'][$namespace] = $path;
        }
    }

    if (isset($composer_config['autoload']['files'])) {
        foreach ($composer_config['autoload']['files'] as $path) {
            $config['autoloads'][] = $path;
        }
    }

    return $config;
}

function config(array $composer_config, array $credentials): array
{
    log('Creating config from composer.json', [
        'composer_config' => $composer_config,
    ]);
    $config = [
        'map' => [],
        'autoloads' => [],
        'excludes' => [],
        'entry-points' => [],
        'executables' => [],
        'packages-directory' => 'vendor',
        'import-file' => 'vendor/autoload.php',
        'packages' => [],
        'aliases' => [],
    ];

    if (isset($composer_config['require'])) {
        foreach ($composer_config['require'] as $packagist_name => $version_pattern) {
            if (! Strings\contains($packagist_name, '/')) {
                continue;
            }

            if ($packagist_name === 'composer/composer') {
                continue;
            }

            if (is_dev_package($version_pattern)) {
                continue;
            }

            $git_url = git_url($packagist_name);

            if ($git_url) {
                $repository = Repositories\prepare($git_url, $credentials);
                if (!Repositories\has_any_tag($repository)) {
                    continue;
                }
                $config['packages'][$git_url] = detect_version($repository, $version_pattern);
            }
        }
    }

    if (isset($composer_config['autoload']['psr-4'])) {
        foreach ($composer_config['autoload']['psr-4'] as $namespace => $path) {
            if (! is_string($path)) {
                continue;
            }

            $namespace = last_character($namespace) === '\\' ? remove_last_character($namespace) : $namespace;
            $path = last_character($path) === '/' ? remove_last_character($path) : $path;

            $config['map'][$namespace] = $path;
        }
    }

    if (isset($composer_config['autoload']['files'])) {
        foreach ($composer_config['autoload']['files'] as $path) {
            $config['autoload'][]= $path;
        }
    }

    return $config;
}

function detect_version(Repository $repository, string $version_pattern): string
{
    debug('Detecting version for repository', [
        'repository' => $repository->identifier(),
        'version_pattern' => $version_pattern,
    ]);
    static $cache = [];
    if (isset($cache[$repository->owner][$repository->repo][$version_pattern])) {
        log('Version found in cache', [
            'repository' => $repository->identifier(),
            'version_pattern' => $version_pattern,
            'version' => $cache[$repository->owner][$repository->repo][$version_pattern],
        ]);
        return $cache[$repository->owner][$repository->repo][$version_pattern];
    }

    $version = get_version($repository, $version_pattern);
    $cache[$repository->owner][$repository->repo][$version_pattern] = $version;
    log('Detected version', [
        'repository' => $repository->identifier(),
        'version_pattern' => $version_pattern,
        'version' => $version,
    ]);

    return $version;
}

function get_version(Repository $repository, string $version_pattern): string
{
    debug('Getting version for repository', [
        'repository' => $repository->identifier(),
        'version_pattern' => $version_pattern,
    ]);
    $normalize_version = str_replace(' ', '', $version_pattern);
    $normalize_version = str_replace('||', '|', $normalize_version);

    // Split the version string by | to handle multiple constraints
    $versions = explode('|', $normalize_version);

    $clean_version = function (string $version) {
        $pattern = '/[\^~<>=|\-*\s]/';

        return preg_replace($pattern, '', $version);
    };

    $version_sorter = function (string $version1, string $version2) use ($clean_version) {
        return compare($clean_version($version2), $clean_version($version1));
    };

    if (count($versions) > 1) {
        usort($versions, $version_sorter);
    }

    $match_version = function ($tags, $version): ?string
    {
        if (Strings\starts_with($version, '^')) {
            $highest_version = str_replace('^', '', $version);
            foreach ($tags as $tag) {
                if (! has_major_change($highest_version, $tag['name']) && compare($tag['name'], $highest_version) >=0) {
                    return $tag['name'];
                }
            }
        } else if (Strings\starts_with($version, '~')) {
            $highest_version = str_replace('~', '', $version);
            foreach ($tags as $tag) {
                if (!has_major_change($highest_version, $tag['name']) && compare($tag['name'], $highest_version) >= 0) {
                    return $tag['name'];
                }
            }
        } else if (Strings\starts_with($version, '>=')) {
            $highest_version = str_replace('>=', '', $version);
            foreach ($tags as $tag) {
                if (!has_major_change($highest_version, $tag['name']) && compare($tag['name'], $highest_version) >= 0) {
                    return $tag['name'];
                }
            }
        } else if (Strings\starts_with($version, '>')) {
            $version = str_replace('>', '', $version);
            foreach ($tags as $tag) {
                if (compare($tag['name'], $version) > 0) {
                    return $tag['name'];
                }
            }
        } else if (Strings\starts_with($version, '<=')) {
            $version = str_replace('<=', '', $version);
            $highest_version = $version;
            foreach ($tags as $tag) {
                if (!has_major_change($highest_version, $tag['name']) && compare($version, $tag['name']) >= 0) {
                    return $tag['name'];
                }
            }
        } else if (Strings\starts_with($version, '<')) {
            $version = str_replace('<', '', $version);
            foreach ($tags as $tag) {
                if (compare($tag['name'], $version) < 0) {
                    return $tag['name'];
                }
            }
        } else if ($version === '@stable') {
            foreach ($tags as $tag) {
                if (is_stable($tag['name'])) {
                    return $tag['name'];
                }
            }
        } else {
            foreach ($tags as $tag) {
                $wildcard = Strings\contains($version, '*') ? '*' : (Strings\contains($version, 'x') ? 'x' : null);

                if ($wildcard) {
                    $version = ltrim($version, 'vV');
                    $tag_name = ltrim($tag['name'], 'vV');
                    $version_parts = explode('.', $version);
                    $tag_parts = explode('.', $tag_name);

                    if ($version_parts[0] === $wildcard) {
                        return $tag['name'];
                    }

                    if ($version_parts[0] === $tag_parts[0]) {
                        if (! isset($version_parts[1]) || $version_parts[1] === $wildcard) {
                            return $tag['name'];
                        }

                        if ($version_parts[1] === $tag_parts[1]) {
                            return $tag_name;
                        }

                        if (! isset($version_parts[2]) || $version_parts[2] === $wildcard) {
                            return $tag['name'];
                        }

                        if ($version_parts[2] === $tag_parts[2]) {
                            return $tag_name;
                        }
                    }
                } else {
                    if (compare($tag['name'], $version) === 0) {
                        return $tag['name'];
                    }
                }
            }
        }

        return null;
    };

    $tags = tags($repository->domain, $repository->owner, $repository->repo, $repository->token);

    usort($tags, function ($tag1, $tag2) {
        return compare($tag2['name'], $tag1['name']);
    });

    while (count($versions) > 0) {
        $version = array_shift($versions);

        $matched_version = $match_version($tags, $version);

        if ($matched_version) {
            return $matched_version;
        }
    }

    throw new CanNotDetectComposerPackageVersionException("Not supported version number $version_pattern defined for package $repository->owner/$repository->repo");
}

function git_url($package): ?string
{
    debug('Getting git URL for package', [
        'package' => $package,
    ]);
    $url = "https://packagist.org/packages/$package.json";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $response = curl_exec($ch);

    if ($response) {
        $data = json_decode($response, true);

        return isset($data['package']['repository']) ? $data['package']['repository'] . '.git' : null;
    } else {
        return null;
    }
}
