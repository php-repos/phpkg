<?php

namespace Phpkg\Application\PackageManager;

use Exception;
use JsonException;
use Phpkg\Classes\Config;
use Phpkg\Classes\Dependency;
use Phpkg\Classes\LinkPair;
use Phpkg\Classes\Meta;
use Phpkg\Classes\NamespaceFilePair;
use Phpkg\Classes\Package;
use Phpkg\Classes\PackageAlias;
use Phpkg\Classes\Project;
use Phpkg\DependencyGraph;
use Phpkg\DependencyGraphs;
use Phpkg\Exception\PreRequirementsFailedException;
use Phpkg\Git\Repository;
use PhpRepos\FileManager\Directory;
use PhpRepos\FileManager\Filename;
use PhpRepos\FileManager\JsonFile;
use PhpRepos\FileManager\Path;
use function Phpkg\Application\Migrator\composer;
use function Phpkg\Comparison\first_is_greater_or_equal;
use function Phpkg\Git\Repositories\download_archive;
use function Phpkg\Git\Repositories\file_content;
use function Phpkg\Git\Repositories\file_exists;
use function Phpkg\Git\Repositories\find_latest_commit_hash;
use function Phpkg\Git\Repositories\find_latest_version;
use function Phpkg\Git\Repositories\find_version_hash;
use function Phpkg\Git\Repositories\has_any_tag;
use function Phpkg\Git\Repositories\tags;
use function Phpkg\Git\Version\compare;
use function Phpkg\Git\Version\has_major_change;
use function Phpkg\System\environment;
use function Phpkg\System\is_windows;
use function PhpRepos\ControlFlow\Conditional\unless;
use function PhpRepos\ControlFlow\Conditional\when;
use function PhpRepos\Datatype\Arr\has;
use function PhpRepos\FileManager\File\exists;

const DEVELOPMENT_VERSION = 'development';

/**
 * @throws PreRequirementsFailedException
 */
function match_highest_version(Repository $repository, string $version): ?string
{
    $tags = tags($repository);

    usort($tags, function ($tag1, $tag2) {
        return compare($tag2['name'], $tag1['name']);
    });

    if (has($tags, fn ($tag) => $tag['name'] === $version)) {
        return $version;
    }

    foreach ($tags as $tag) {
        if (! has_major_change($version, $tag['name'])
            && first_is_greater_or_equal(fn () => compare($version, $tag['name']) >= 0)
        ) {
            return $tag['name'];
        }
    }

    throw new PreRequirementsFailedException("Can not find $version for package $repository->owner/$repository->repo.");
}

/**
 * @throws Exception
 */
function get_latest_version(Repository $repository): string
{
    return has_any_tag($repository)
        ? find_latest_version($repository)
        : DEVELOPMENT_VERSION;
}

function is_development_version(string $version): bool
{
    return $version === DEVELOPMENT_VERSION;
}

/**
 * @throws Exception
 */
function detect_hash(Repository $repository): string
{
    return is_development_version($repository->version)
        ? find_latest_commit_hash($repository)
        : find_version_hash($repository);
}

function cache_path(Package $package): Path
{
    return environment()->temp
        ->append("installer/{$package->value->domain}/{$package->value->owner}/{$package->value->repo}/{$package->value->hash}");
}

function package_path(Project $project, Package $package): Path
{
    return $project->packages_directory->append("{$package->value->owner}/{$package->value->repo}");
}

/**
 * @throws JsonException
 * @throws Exception
 */
function load_local_config(Path $root): Config
{
    $config_file = $root->append('phpkg.config.json');
    if (exists($config_file)) {
        return Config::from_array(JsonFile\to_array($config_file));
    }

    if (exists($root->append('composer.json'))) {
        return Config::from_array(composer(JsonFile\to_array($root->append('composer.json'))));
    }

    return Config::init();
}

/**
 * @throws JsonException
 * @throws Exception
 */
function load_config(Package $package): Config
{
    $cache_path = cache_path($package);

    if (Directory\exists($cache_path)) {
        return load_local_config($cache_path);
    }

    if (file_exists($package->value, 'phpkg.config.json')) {
        return Config::from_array(json_decode(json: file_content($package->value, 'phpkg.config.json'), associative: true, flags: JSON_THROW_ON_ERROR));
    }

    if (file_exists($package->value, 'composer.json')) {
        return Config::from_array(
            composer(
                json_decode(
                    json: file_content($package->value, 'composer.json'),
                    associative: true,
                    flags: JSON_THROW_ON_ERROR
                )
            )
        );
    }

    return Config::init();
}

function delete_package(Project $project, Package $package): bool
{
    if (is_windows()) {
        Directory\ls_recursively(package_path($project, $package))->vertices()->each(fn ($filename) => chmod($filename, 0777));
    }

    return Directory\delete_recursive(package_path($project, $package));
}

function commit(Project $project): bool
{
    $config = [
        'map' => [],
        'autoloads' => [],
        'excludes' => [],
        'entry-points' => [],
        'executables' => [],
        'import-file' => 'phpkg.imports.php',
        'packages-directory' => 'Packages',
        'packages' => [],
    ];

    $project->config->map->each(function (NamespaceFilePair $namespace_file) use (&$config) {
        $config['map'][$namespace_file->key] = $namespace_file->value->string();
    });
    $project->config->autoloads->each(function (Filename $filename) use (&$config) {
        $config['autoloads'][] = $filename->string();
    });
    $project->config->excludes->each(function (Filename $filename) use (&$config) {
        $config['excludes'][] = $filename->string();
    });
    $project->config->entry_points->each(function (Filename $filename) use (&$config) {
        $config['entry-points'][] = $filename->string();
    });
    $project->config->executables->each(function (LinkPair $link) use (&$config) {
        $config['executables'][$link->key->string()] = $link->value->string();
    });
    $config['packages-directory'] = $project->config->packages_directory->string();
    $project->config->packages->each(function (Package $package) use (&$config) {
        $config['packages'][$package->key] = $package->value->version;
    });
    $project->config->aliases->each(function (PackageAlias $package_alias) use (&$config) {
        $config['aliases'][$package_alias->key] = $package_alias->value;
    });

    $meta = $project->meta->packages->reduce(function (array $packages, Package $package) {
        $packages['packages'][$package->key] = meta($package->value);

        return $packages;
    }, ['packages' => []]);

    return JsonFile\write($project->config_file, $config)
        && JsonFile\write($project->config_lock_file, $meta);
}

/**
 * @param Repository $repository
 * @return array{owner: string, repo: string, version: string, hash: string}
 */
function meta(Repository $repository): array
{
    return [
        'owner' => $repository->owner,
        'repo' => $repository->repo,
        'version' => $repository->version,
        'hash' => $repository->hash,
    ];
}

/**
 * @throws Exception
 */
function manage_dependencies(Project $project, DependencyGraph $dependency_graph): void
{
    $dependency_graph = DependencyGraphs\resolve($dependency_graph);

    $project->meta->packages
        ->each(function (Package $package) use ($project, $dependency_graph) {
            $dependency = Dependency::from_package($package);

            if (!DependencyGraphs\has_similar_dependency($dependency_graph, $dependency)) {
                return;
            }

            $highest_version = DependencyGraphs\highest_version_of_dependency($dependency_graph, $dependency);

            when(
                $project->check_semantic_versioning && has_major_change($highest_version->value->value->version, $dependency->value->value->version),
                fn () => throw new PreRequirementsFailedException('There is a major upgrade in the version number. Make sure it is a compatible change and if it is, try updating by --force.')
            );
        });

    $project->meta->packages
        ->filter(fn (Package $package) => ! DependencyGraphs\has_identical_dependency($dependency_graph, Dependency::from_package($package)))
        ->each(function (Package $unused_package) use ($project, $dependency_graph) {
            delete_package($project, $unused_package);
            $project->meta->packages->forget(fn (Package $package)
                => $package->value->owner === $unused_package->value->owner
                    && $package->value->repo === $unused_package->value->repo);
        });

    $project->meta->packages
        ->filter(fn (Package $package) => DependencyGraphs\has_identical_dependency($dependency_graph, Dependency::from_package($package)))
        ->each(function (Package $package) use ($project, $dependency_graph) {
            $dependency = Dependency::from_package($package);
            $highest_version = DependencyGraphs\highest_version_of_dependency($dependency_graph, $dependency);

            if ($highest_version->key !== $dependency->key) {
                delete_package($project, $dependency->value);
                $project->meta->packages->forget(fn (Package $installed_package) => Dependency::from_package($installed_package)->key === $dependency->key);
            }
        });

    DependencyGraphs\foreach_dependency($dependency_graph, function (Dependency $dependency) use ($project, $dependency_graph) {
        $root = cache_path($dependency->value);
        unless(Directory\exists($root), fn () => Directory\make_recursive($root)
            && download_archive($dependency->value->value, $root));
        Directory\renew_recursive(package_path($project, $dependency->value));
        Directory\preserve_copy_recursively(
            cache_path($dependency->value),
            package_path($project, $dependency->value)
        );
        $project->meta->packages->push($dependency->value);
    });
}

/**
 * @throws JsonException
 * @throws Exception
 */
function add_dependency(Project $project, DependencyGraph &$dependency_graph, Package $package): Dependency
{
    $package->value->hash = detect_hash($package->value);
    $dependency = Dependency::from_package($package);

    if (DependencyGraphs\has_identical_dependency($dependency_graph, $dependency)) {
        return $dependency;
    }

    $dependency_graph = DependencyGraphs\add($dependency_graph, $dependency);

    $dependency_graph = load_config($dependency->value)->packages->reduce(function (DependencyGraph $dependency_graph, Package $sub_package) use ($project, $dependency) {
        $sub_dependency = add_dependency($project, $dependency_graph, $sub_package);
        return DependencyGraphs\add_sub_dependency($dependency_graph, $dependency, $sub_dependency);
    }, $dependency_graph);

    return $dependency;
}

/**
 * @throws Exception
 */
function install(Project $project): void
{
    Directory\exists_or_create($project->packages_directory);

    if (! exists($project->config_lock_file)) {
        $dependency_graph = DependencyGraph::empty();
        $project->config->packages->each(function (Package $package) use ($project, $dependency_graph) {
            add_dependency($project, $dependency_graph, $package);
        });

        DependencyGraphs\foreach_dependency($dependency_graph, function (Dependency $dependency) use ($project) {
            $project->meta->packages->push($dependency->value);
        });

        commit($project);
    }

    $project->meta->packages->each(function (Package $package) use ($project) {
        $root = package_path($project, $package);
        if (! Directory\exists($root)) {
            Directory\make_recursive($root);
        }
        download_archive($package->value, $root);
    });
}

/**
 * @throws JsonException
 * @throws Exception
 */
function add(Project $project, Package $package): Dependency
{
    $dependency_graph = DependencyGraph::for($project);

    $dependency = add_dependency($project, $dependency_graph, $package);

    manage_dependencies($project, $dependency_graph);

    return $dependency;
}

/**
 * @throws JsonException
 * @throws Exception
 */
function update(Project $project, Package $package): Dependency
{
    $dependency_graph = DependencyGraph::for($project);

    $dependency = add_dependency($project, $dependency_graph, $package);

    manage_dependencies($project, $dependency_graph);

    return $dependency;
}

function is_main_dependency(Project $project, Package $package): bool
{
    return $project->config->packages->has(fn (Package $main_package)
        => $main_package->value->owner === $package->value->owner
            && $main_package->value->repo === $package->value->repo);
}

function remove_dependency(Project $project, DependencyGraph $dependency_graph, Dependency $dependency): DependencyGraph
{
    $dependency = DependencyGraphs\find_dependency($dependency_graph, $dependency);

    if (! is_main_dependency($project, $dependency->value) && count(DependencyGraphs\dependents($dependency_graph, $dependency)) === 0) {
        $dependencies = DependencyGraphs\dependencies($dependency_graph, $dependency);
        $dependency_graph = DependencyGraphs\remove($dependency_graph, $dependency);

        foreach ($dependencies as $sub_dependency) {
            remove_dependency($project, $dependency_graph, $sub_dependency);
        }
    }

    return $dependency_graph;
}

/**
 * @throws Exception
 */
function remove(Project $project, Package $package): void
{
    $dependency_graph = DependencyGraph::for($project);

    remove_dependency($project, $dependency_graph, Dependency::from_package($package));

    manage_dependencies($project, $dependency_graph);
}

/**
 * @throws JsonException
 * @throws Exception
 */
function migrate(Project $project): void
{
    $config = load_local_config($project->root);
    $config->packages_directory = new Filename('vendor');
    $meta = Meta::init();

    $project->config($config);
    $project->meta = $meta;

    Directory\delete_recursive($project->packages_directory);

    $dependency_graph = DependencyGraph::empty();

    $config->packages->each(function (Package $package) use ($project, $dependency_graph) {
        add_dependency($project, $dependency_graph, $package);
    });

    manage_dependencies($project, $dependency_graph);
}
