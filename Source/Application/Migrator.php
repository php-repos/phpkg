<?php

namespace Phpkg\Application\Migrator;

use Phpkg\Exception\CanNotDetectComposerPackageVersionException;
use Phpkg\Git\Repository;
use function Phpkg\Application\PackageManager\meta_array;
use function Phpkg\Comparison\first_is_greater_or_equal;
use function Phpkg\Git\Repositories\has_any_tag;
use function Phpkg\Git\Repositories\tags;
use function Phpkg\Git\Version\compare;
use function Phpkg\Git\Version\has_major_change;
use function Phpkg\Git\Version\is_stable;
use function Phpkg\Packagist\git_url;
use function PhpRepos\Datatype\Str\last_character;
use function PhpRepos\Datatype\Str\remove_last_character;

function is_dev_package(string $version_pattern): bool
{
    return str_contains($version_pattern, 'dev');
}

function composer(array $composer_config): array
{
    $config = [];

    if (isset($composer_config['require'])) {
        foreach ($composer_config['require'] as $packagist_name => $version_pattern) {
            if (! str_contains($packagist_name, '/')) {
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
                $repository = Repository::from_url($git_url);
                if (!has_any_tag($repository)) {
                    continue;
                }
                $config['packages'][$git_url] = get_version($repository, $version_pattern);
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

    $config['import-file'] = 'vendor/autoload.php';

    return $config;
}

function get_version(Repository $repository, string $version_pattern): string
{
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
        if (str_starts_with($version, '^')) {
            $highest_version = str_replace('^', '', $version);
            foreach ($tags as $tag) {
                if (! has_major_change($highest_version, $tag['name']) && first_is_greater_or_equal(fn () => compare($tag['name'], $highest_version))) {
                    return $tag['name'];
                }
            }
        } else if (str_starts_with($version, '~')) {
            $highest_version = str_replace('~', '', $version);
            foreach ($tags as $tag) {
                if (!has_major_change($highest_version, $tag['name']) && compare($tag['name'], $highest_version) >= 0) {
                    return $tag['name'];
                }
            }
        } else if (str_starts_with($version, '>=')) {
            $highest_version = str_replace('>=', '', $version);
            foreach ($tags as $tag) {
                if (!has_major_change($highest_version, $tag['name']) && compare($tag['name'], $highest_version) >= 0) {
                    return $tag['name'];
                }
            }
        } else if (str_starts_with($version, '>')) {
            $version = str_replace('>', '', $version);
            foreach ($tags as $tag) {
                if (compare($tag['name'], $version) > 0) {
                    return $tag['name'];
                }
            }
        } else if (str_starts_with($version, '<=')) {
            $version = str_replace('<=', '', $version);
            $highest_version = $version;
            foreach ($tags as $tag) {
                if (!has_major_change($highest_version, $tag['name']) && compare($version, $tag['name']) >= 0) {
                    return $tag['name'];
                }
            }
        } else if (str_starts_with($version, '<')) {
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
                $wildcard = str_contains($version, '*') ? '*' : (str_contains($version, 'x') ? 'x' : null);

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

    $tags = tags($repository);

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

function composer_lock(array $lock_content): array
{
    $meta = ['packages' => []];

    foreach ($lock_content['packages'] as $package) {
        if (! str_contains($package['name'], '/')) {
            continue;
        }

        if ($package['name'] === 'composer/composer') {
            continue;
        }

        if (is_dev_package($package['version'])) {
            continue;
        }
        $repository = Repository::from_url($package['source']['url']);
        $repository->version = $package['version'];
        $repository->hash = $package['source']['reference'];

        $meta['packages'][$package['source']['url']] = meta_array($repository);
    }

    return $meta;
}
