<?php

namespace Phpkg\Solution\Dependencies;

use Phpkg\Solution\Data\Commit;
use Phpkg\Solution\Data\Package;
use Phpkg\Solution\Data\Repository;
use Phpkg\Solution\Data\Version;
use Phpkg\Solution\Exceptions\DependencyResolutionException;
use Phpkg\Solution\Exceptions\VersionIncompatibilityException;
use Phpkg\Solution\PHPKGCSP;
use Phpkg\Solution\PHPKGSAT;
use Phpkg\Solution\Commits;
use Phpkg\Solution\Repositories;
use Phpkg\Infra\Arrays;
use PhpRepos\SimpleCSP\CSPs;
use PhpRepos\SimpleCSP\SATs;
use function Phpkg\Infra\Logs\log;
use function Phpkg\Infra\Logs\debug;

function setup(Commit $commit, array $config, string $root): Package
{
    log('Setting up package from commit', [
        'commit' => $commit->identifier(),
        'config' => $config,
        'root' => $root,
    ]);

    $package = new Package($commit, $config);
    $package->root($root);

    return $package;
}

function from_meta(string $url, array $meta, array $config, string $root): Package
{
    log('Creating package from meta', [
        'url' => $url,
        'meta' => $meta,
        'config' => $config,
        'root' => $root,
    ]);

    $repository = new Repository($url, $meta['domain'] ?? 'github.com', $meta['owner'], $meta['repo'], null);
    $version = new Version($repository, $meta['version']);
    $commit = new Commit($version, $meta['hash']);
    $package = new Package($commit, $config);
    $package = $package->root($root);
    if (isset($meta['checksum'])) {
        $package = $package->checksum($meta['checksum']);
    }
    return $package;
}

function loaded_package(Commit $commit, array $config): Package
{
    return new Package($commit, $config);
}

/**
 * @param Package[] $packages
 * @param array $project_config
 * @param bool $ignore_version_compatibility
 * @return array
 * @throws DependencyResolutionException
 * @throws VersionIncompatibilityException
 */
function resolve(array $packages, array $project_config, bool $ignore_version_compatibility): array
{
    log('Resolving dependencies for packages', [
        'packages' => $packages,
        'project_config' => $project_config,
        'ignore_version_compatibility' => $ignore_version_compatibility,
    ]);
    $csp = new PHPKGCSP($packages, $project_config, $ignore_version_compatibility);
    $solutions = CSPs\solve($csp);
    debug('CSP Solutions', ['solutions' => Arrays\map($solutions, fn ($solution) => Arrays\map($solution, fn ($assignment) => [
        'variable' => $assignment['variable']->identifier(),
        'value' => is_string($assignment['value']) ? $assignment['value'] : $assignment['value']->identifier(),
    ]))]);

    if (count($solutions) === 0 && !$ignore_version_compatibility && count(CSPs\solve(new PHPKGCSP($packages, $project_config, true)))) {
        throw new VersionIncompatibilityException('There is version incompatibility between packages. You might force using --force option.');
    }

    if (count($solutions) === 0) {
        if (count($project_config['packages']) === 0) return [];
        throw new DependencyResolutionException('Could not resolve all dependencies. Please use verbose mode to find the issue and open a GitHub issue if needed.');
    }

    $sat = new PHPKGSAT($solutions, $project_config, $ignore_version_compatibility);
    debug('SAT clauses have been constructed', ['variables' => $sat->variables, 'clauses' => $sat->clauses]);

    $optimal_solution = SATs\max($sat);
    debug('Optimal SAT Solution', ['optimal_solution' => $optimal_solution]);
    if ($optimal_solution === null) {
        $optimal_solution = [];
    }
    if (count($project_config['packages']) > count(Arrays\filter($optimal_solution, fn ($assignment) => $assignment['value'] === true))) {
        throw new DependencyResolutionException('Could not resolve all dependencies. Please use verbose mode to find the issue and open a GitHub issue if needed.');
    }

    $new_packages = [];

    foreach ($optimal_solution as $assignment) {
        if ($assignment['value'] === false) continue;
        $new_packages[] = $assignment['variable'];
    }

    return $new_packages;
}

function required_main_package(array $config, Package $package): ?Version
{
    debug('Checking for required main package in config', [
        'config' => $config,
        'package' => $package->identifier(),
    ]);

    foreach ($config['packages'] as $version) {
        if (Repositories\are_equal($version->repository, $package->commit->version->repository)) {
            return $version;
        }
    }

    return null;
}

function is_main_package(array $config, Package $package): bool
{
    debug('Checking if package is main package', [
        'config' => $config,
        'package' => $package->identifier(),
    ]);

    return required_main_package($config, $package) !== null;
}

function claims_same_namespaces(array $config, Package $package): bool
{
    debug('Checking if package claims same namespaces as in config', [
        'config' => $config,
        'package' => $package->identifier(),
    ]);

    foreach ($package->config['map'] as $claimed_namespace => $defined_path) {
        if ($claimed_namespace === 'Tests') {
            continue;
        }
        if (Arrays\has($config['map'], fn (string $path, string $namespace) => $namespace === $claimed_namespace)) {
            return true;
        }
    }
    return false;
}

function update_main_packages(array $packages, array $config): array
{
    log('Updating main packages in the list of packages', [
        'packages' => Arrays\map($packages, fn($package) => $package->identifier()),
        'config' => $config,
    ]);

    foreach ($config['packages'] as $package_url => $version) {
        foreach ($packages as $package) {
            if (Repositories\are_equal($version->repository, $package->commit->version->repository)) {
                $config['packages'][$package_url] = $package->commit->version;
            }
        }
    }

    return $config;
}

function group_by_operation(array $old_packages, array $new_packages): array
{
    log('Grouping packages by operation', [
        'old_packages' => Arrays\map($old_packages, fn($package) => $package->identifier()),
        'new_packages' => Arrays\map($new_packages, fn($package) => $package->identifier()),
    ]);

    $additions = Arrays\filter($new_packages, fn (Package $new_package)
        => !Arrays\has($old_packages, fn (Package $old_package)
            => Repositories\are_equal($new_package->commit->version->repository, $old_package->commit->version->repository)));

    $deletions = Arrays\filter($old_packages, fn (Package $old_package)
        => !Arrays\has($new_packages, fn (Package $new_package)
        => Repositories\are_equal($new_package->commit->version->repository, $old_package->commit->version->repository)));

    $updates = Arrays\filter($new_packages, fn (Package $new_package)
        => Arrays\has($old_packages, fn (Package $old_package)
            => Repositories\are_equal($new_package->commit->version->repository, $old_package->commit->version->repository)
            && !Commits\are_equal($new_package->commit, $old_package->commit)));

    $intacts = Arrays\filter($new_packages, fn (Package $new_package)
        => Arrays\has($old_packages, fn (Package $old_package)
            => Repositories\are_equal($new_package->commit->version->repository, $old_package->commit->version->repository)
            && Commits\are_equal($new_package->commit, $old_package->commit)));

    return [
        'additions' => $additions,
        'deletions' => $deletions,
        'updates' => $updates,
        'intacts' => $intacts,
    ];
}
