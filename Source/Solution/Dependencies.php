<?php

namespace Phpkg\Solution\Dependencies;

use Phpkg\Solution\Data\Commit;
use Phpkg\Solution\Data\Package;
use Phpkg\Solution\Data\Repository;
use Phpkg\Solution\Data\Version;
use Phpkg\Solution\Exceptions\DependencyResolutionException;
use Phpkg\Solution\Exceptions\VersionIncompatibilityException;
use Phpkg\Solution\Paths;
use Phpkg\Solution\PHPKGCSP;
use Phpkg\Solution\PHPKGSAT;
use Phpkg\Solution\Repositories;
use Phpkg\Infra\Arrays;
use Phpkg\Infra\Files;
use Phpkg\Infra\GitHosts;
use PhpRepos\SimpleCSP\CSPs;
use PhpRepos\SimpleCSP\SATs;
use function Phpkg\Infra\Logs\log;
use function Phpkg\Infra\Logs\debug;

function find(Repository $repository, array $packages): ?Package
{
    log('Finding package in dependencies', [
        'repository' => $repository->identifier(),
    ]);

    return Arrays\first($packages, fn (Package $package) => Repositories\are_equal($repository, $package->commit->version->repository));
}

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

    $new_packages = [];

    if (count($solutions) > 0) {
        $sat = new PHPKGSAT($solutions, $project_config, $ignore_version_compatibility);
        debug('SAT clauses have been constructed', ['variables' => $sat->variables, 'clauses' => $sat->clauses]);

        $optimal_solution = SATs\max($sat);
        debug('Optimal SAT Solution', ['optimal_solution' => $optimal_solution]);

        if ($optimal_solution !== null) {
            foreach ($optimal_solution as $assignment) {
                if ($assignment['value'] === true) {
                    $new_packages[] = $assignment['variable'];
                }
            }
        }
    }

    if (count($project_config['packages']) > count($new_packages)) {
        throw new DependencyResolutionException('Could not resolve all dependencies. Please use verbose mode to find the issue and open a GitHub issue if needed.');
    }

    return $new_packages;
}

/**
 * @param Package[] $packages
 */
function has_repository(array $packages, Repository $repository): bool
{
    log('Checking if packages contain repository', [
        'repository' => $repository->identifier(),
    ]);

    return Arrays\has(
        $packages,
        fn (Package $package) => Repositories\are_equal($repository, $package->commit->version->repository)
    );
}

function download_to(Package $package, string $destination): bool
{
    log('Downloading package to destination', [
        'package' => $package->identifier(),
        'destination' => $destination,
    ]);

    Paths\ensure_directory_exists($destination);

    $archive = Paths\under($destination, $package->commit->hash . '.zip');
    $download_status = GitHosts\download(
        $package->commit->version->repository->domain,
        $package->commit->version->repository->owner,
        $package->commit->version->repository->repo,
        $package->commit->hash,
        $package->commit->version->repository->token,
        $archive,
    );

    if (!$download_status) {
        log('Failed to download package archive', [
            'package' => $package->identifier(),
            'archive' => $archive,
        ]);
        return false;
    }

    log('Unpacking package archive', [
        'archive' => $archive,
    ]);

    // Unzip the file in the same directory
    if (!Files\unpack($archive, $destination)) {
        return false;
    }

    $zip_root = Paths\under($destination, Files\zip_root($archive));

    Paths\delete_file($archive);

    $success = Paths\preserve_copy_directory_content($zip_root, $destination);

    return $success && Paths\delete_directory($zip_root);
}

function required_main_package(array $config, Package $package): ?Version
{
    debug('Checking for required main package in config', [
        'config' => $config,
        'package' => $package->identifier(),
    ]);

    foreach ($config['packages'] as $package_url => $version) {
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

function contains_package(array $packages, Package $package): bool
{
    log('Checking if packages contain specific package', [
        'packages' => Arrays\map($packages, fn(Package $p) => $p->identifier()),
        'package' => $package->identifier(),
    ]);
    
    return Arrays\has($packages, fn (Package $p) => $p->identifier() === $package->identifier());
}

function contains_repository(array $packages, Repository $repository): bool
{
    log('Checking if packages contain specific repository', [
        'packages' => Arrays\map($packages, fn(Package $p) => $p->identifier()),
        'repository' => $repository->identifier(),
    ]);

    return Arrays\has($packages, fn (Package $p) => Repositories\are_equal($p->commit->version->repository, $repository));
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
        foreach ($packages as $index => $package) {
            if (Repositories\are_equal($version->repository, $package->commit->version->repository)) {
                $config['packages'][$package_url] = $package->commit->version;
            }
        }
    }

    return $config;
}
