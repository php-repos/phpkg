<?php

namespace Phpkg\Application\PackageManager;

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
use Phpkg\Git\Repositories;
use PhpRepos\FileManager\Directory;
use PhpRepos\FileManager\File;
use PhpRepos\FileManager\Filename;
use PhpRepos\FileManager\JsonFile;
use PhpRepos\FileManager\Path;
use function Phpkg\Application\Migrator\composer;
use function Phpkg\Application\Migrator\composer_lock;
use function Phpkg\Comparison\first_is_greater_or_equal;
use function Phpkg\Git\Version\compare;
use function Phpkg\Git\Version\has_major_change;
use function Phpkg\System\environment;
use function Phpkg\System\is_windows;
use function PhpRepos\ControlFlow\Conditional\unless;
use function PhpRepos\ControlFlow\Conditional\when;
use function PhpRepos\Datatype\Arr\has;

const DEVELOPMENT_VERSION = 'development';

function match_highest_version(Repository $repository, string $version): ?string
{
    $tags = Repositories\tags($repository);

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

function get_latest_version(Repository $repository): string
{
    return Repositories\has_any_tag($repository)
        ? Repositories\find_latest_version($repository)
        : DEVELOPMENT_VERSION;
}

function is_development_version(string $version): bool
{
    return $version === DEVELOPMENT_VERSION;
}

function detect_hash(Repository $repository): string
{
    static $cache = [];

    if (isset($cache[$repository->owner][$repository->repo][$repository->version])) {
        return $cache[$repository->owner][$repository->repo][$repository->version];
    }

    $hash = is_development_version($repository->version)
        ? Repositories\find_latest_commit_hash($repository)
        : Repositories\find_version_hash($repository);

    $cache[$repository->owner][$repository->repo][$repository->version] = $hash;

    return $hash;
}

function temp_path(Package $package): Path
{
    return environment()->temp
        ->append("installer/{$package->value->domain}/{$package->value->owner}/{$package->value->repo}/{$package->value->hash}");
}

function package_path(Project $project, Package $package): Path
{
    return $project->packages_directory->append("{$package->value->owner}/{$package->value->repo}");
}

function config_from_disk(Path $root): Config
{
    return Config::from_array(JsonFile\to_array($root->append('phpkg.config.json')));
}

function config(Dependency $dependency): Config
{
    static $cache = [];

    if (isset($cache[$dependency->key])) {
        return $cache[$dependency->key];
    }

    if (Directory\exists(temp_path($dependency->value)) && File\exists(temp_path($dependency->value)->append('phpkg.config.json'))) {
        $config = Config::from_array(JsonFile\to_array(temp_path($dependency->value)->append('phpkg.config.json')));
    } else if (Repositories\file_exists($dependency->value->value, 'phpkg.config.json')) {
        $config = Config::from_array(json_decode(json: Repositories\file_content($dependency->value->value, 'phpkg.config.json'), associative: true, flags: JSON_THROW_ON_ERROR));
    } else if (Repositories\file_exists($dependency->value->value, 'composer.json')) {
        $config = Config::from_array(
            composer(
                json_decode(
                    json: Repositories\file_content($dependency->value->value, 'composer.json'),
                    associative: true,
                    flags: JSON_THROW_ON_ERROR
                )
            )
        );
    }

    if (! isset($config)) {
        throw new PreRequirementsFailedException('The package you provided is neither a valid `phpkg` package nor a `composer` package. Please ensure that you are using a supported package type.');
    }

    $cache[$dependency->key] = $config;

    return $config;
}

function meta(Dependency $dependency): Meta
{
    static $cache = [];

    if (isset($cache[$dependency->key])) {
        return $cache[$dependency->key];
    }

    if (File\exists(temp_path($dependency->value)->append('phpkg.config-lock.json'))) {
        $meta = Meta::from_array(JsonFile\to_array(temp_path($dependency->value)->append('phpkg.config-lock.json')));
    } else if (Repositories\file_exists($dependency->value->value, 'phpkg.config-lock.json')) {
        $meta = Meta::from_array(json_decode(json: Repositories\file_content($dependency->value->value, 'phpkg.config-lock.json'), associative: true, flags: JSON_THROW_ON_ERROR));
    } else if (Repositories\file_exists($dependency->value->value, 'composer.lock')) {
        $meta = Meta::from_array(
            composer_lock(
                json_decode(
                    json: Repositories\file_content($dependency->value->value, 'composer.lock'),
                    associative: true,
                    flags: JSON_THROW_ON_ERROR
                )
            )
        );
    } else {
        $meta = Meta::init();
    }

    $cache[$dependency->key] = $meta;

    return $meta;
}

function get_hash(Dependency $dependency, Package $package): string
{
    $meta = meta($dependency);

    $hash = $meta->packages->has(fn (Package $required_package) => $required_package->value->owner === $package->value->owner && $required_package->value->repo === $package->value->repo && $required_package->value->version === $package->value->version)
        ? $meta->packages->first(fn (Package $required_package) => $required_package->value->owner === $package->value->owner && $required_package->value->repo === $package->value->repo && $required_package->value->version === $package->value->version)->value->hash
        : detect_hash($package->value);

    return $hash !== '' ? $hash : detect_hash($package->value);
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
    $config = config_to_array($project->config);
    $meta = meta_to_array($project->meta);

    return JsonFile\write($project->config_file, $config)
        && JsonFile\write($project->config_lock_file, $meta);
}

function meta_to_array(Meta $meta): array
{
    return $meta->packages->reduce(function (array $packages, Package $package) {
        $packages['packages'][$package->key] = meta_array($package->value);

        return $packages;
    }, ['packages' => []]);
}

function config_to_array(Config $config): array
{
    $config_array = [
        'map' => [],
        'autoloads' => [],
        'excludes' => [],
        'entry-points' => [],
        'executables' => [],
        'import-file' => 'phpkg.imports.php',
        'packages-directory' => 'Packages',
        'packages' => [],
    ];

    $config->map->each(function (NamespaceFilePair $namespace_file) use (&$config_array) {
        $config_array['map'][$namespace_file->key] = $namespace_file->value->string();
    });
    $config->autoloads->each(function (Filename $filename) use (&$config_array) {
        $config_array['autoloads'][] = $filename->string();
    });
    $config->excludes->each(function (Filename $filename) use (&$config_array) {
        $config_array['excludes'][] = $filename->string();
    });
    $config->entry_points->each(function (Filename $filename) use (&$config_array) {
        $config_array['entry-points'][] = $filename->string();
    });
    $config->executables->each(function (LinkPair $link) use (&$config_array) {
        $config_array['executables'][$link->key->string()] = $link->value->string();
    });
    $config_array['import-file'] = $config->import_file->string();
    $config_array['packages-directory'] = $config->packages_directory->string();
    $config->packages->each(function (Package $package) use (&$config_array) {
        $config_array['packages'][$package->key] = $package->value->version;
    });
    $config->aliases->each(function (PackageAlias $package_alias) use (&$config_array) {
        $config_array['aliases'][$package_alias->key] = $package_alias->value;
    });

    return $config_array;
}

/**
 * @param Repository $repository
 * @return array{owner: string, repo: string, version: string, hash: string}
 */
function meta_array(Repository $repository): array
{
    return [
        'owner' => $repository->owner,
        'repo' => $repository->repo,
        'version' => $repository->version,
        'hash' => $repository->hash,
    ];
}

function download(Dependency $dependency, Path $root): bool
{
    $downloaded = Repositories\download_archive($dependency->value->value, $root);

    if (! File\exists($root->append('phpkg.config.json'))) {
        JsonFile\write($root->append('phpkg.config.json'), config_to_array(config($dependency)));
    }

    if (! File\exists($root->append('phpkg.config-lock.json'))) {
        JsonFile\write($root->append('phpkg.config-lock.json'), meta_to_array(meta($dependency)));
    }

    return $downloaded;
}

function manage_dependencies(Project $project, DependencyGraph $dependency_graph): void
{
    $dependency_graph = DependencyGraphs\resolve($dependency_graph);

    $dependency_graph->vertices->each(function (Dependency $dependency) use ($project, $dependency_graph) {
        DependencyGraphs\dependencies($dependency_graph, $dependency)->each(function (Dependency $dependency) use ($project, $dependency_graph) {
            $number_of_used = $project->config->packages->has(fn (Package $main_package) => $dependency->value->value->owner === $main_package->value->owner && $dependency->value->value->repo === $main_package->value->repo) ? 1 : 0;

            $project->config->packages->each(function (Package $main_package) use ($project, $dependency_graph, $dependency, &$number_of_used) {
                $main_dependency = DependencyGraphs\find_package_dependency($dependency_graph, $main_package);
                $number_of_used += DependencyGraphs\dependencies($dependency_graph, $main_dependency)->filter(fn (Dependency $sub_dependency) => $dependency->key === $sub_dependency->key)->count();
            });

            if ($number_of_used === 0) {
                DependencyGraphs\remove($dependency_graph, $dependency);
            }
        });
    });

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

    $dependency_graph->vertices->each(function (Dependency $dependency) use ($project, $dependency_graph) {
        $root = temp_path($dependency->value);
        unless(Directory\exists($root), fn () => Directory\make_recursive($root) && download($dependency, $root));
        Directory\renew_recursive(package_path($project, $dependency->value));
        Directory\preserve_copy_recursively(
            temp_path($dependency->value),
            package_path($project, $dependency->value)
        );
        $project->meta->packages->push($dependency->value);
    });
}

function add_dependency(Project $project, DependencyGraph &$dependency_graph, Package $package): Dependency
{
    $dependency = Dependency::from_package($package);

    if (DependencyGraphs\has_identical_dependency($dependency_graph, $dependency)) {
        return $dependency;
    }

    $dependency_graph = DependencyGraphs\add($dependency_graph, $dependency);

    $dependency_graph = config($dependency)->packages->reduce(function (DependencyGraph $dependency_graph, Package $sub_package) use ($project, $dependency, $package) {
        $sub_package->value->hash = get_hash($dependency, $sub_package);
        $sub_dependency = add_dependency($project, $dependency_graph, $sub_package);
        return DependencyGraphs\add_sub_dependency($dependency_graph, $dependency, $sub_dependency);
    }, $dependency_graph);

    return $dependency;
}

function install(Project $project): void
{
    Directory\exists_or_create($project->packages_directory);

    if (! File\exists($project->config_lock_file)) {
        $dependency_graph = DependencyGraph::empty();
        $project->config->packages->each(function (Package $package) use ($project, $dependency_graph) {
            $package->value->hash = detect_hash($package->value);
            add_dependency($project, $dependency_graph, $package);
        });

        $dependency_graph->vertices->each(function (Dependency $dependency) use ($project) {
            $project->meta->packages->push($dependency->value);
        });

        commit($project);
    }

    $project->meta->packages->each(function (Package $package) use ($project) {
        $root = package_path($project, $package);
        if (! Directory\exists($root)) {
            Directory\make_recursive($root);
        }

        download(Dependency::from_package($package), $root);
    });
}

function add(Project $project, Package $package)
{
    $package->value->hash = detect_hash($package->value);
    $dependency_graph = DependencyGraph::for($project);
    add_dependency($project, $dependency_graph, $package);
    manage_dependencies($project, $dependency_graph);
}

function update(Project $project, Package $package)
{
    $package->value->hash = detect_hash($package->value);
    $dependency_graph = DependencyGraph::for($project);
    $old_dependency = DependencyGraphs\find_package_dependency($dependency_graph, $package);
    $new_dependency = add_dependency($project, $dependency_graph, $package);
    $dependency_graph = DependencyGraphs\swap($dependency_graph, $old_dependency, $new_dependency);
    DependencyGraphs\remove($dependency_graph, $old_dependency);
    manage_dependencies($project, $dependency_graph);
}

function remove(Project $project, Package $package): void
{
    $dependency_graph = DependencyGraph::for($project);
    $dependency = Dependency::from_package($package);
    DependencyGraphs\dependencies($dependency_graph, $dependency)->each(function (Dependency $dependency) use ($project, $dependency_graph) {
        $number_of_used = 1;

        $project->config->packages->each(function (Package $main_package) use ($project, $dependency_graph, $dependency, &$number_of_used) {
            $main_installed_package = $project->meta->packages->first(fn (Package $installed_package) => $installed_package->value->owner === $main_package->value->owner && $installed_package->value->repo === $main_package->value->repo);
            $number_of_used += DependencyGraphs\dependencies($dependency_graph, Dependency::from_package($main_installed_package))->filter(fn (Dependency $sub_dependency) => $dependency->key === $sub_dependency->key)->count();
        });

        if ($number_of_used === 1) {
            DependencyGraphs\remove($dependency_graph, $dependency);
        }
    });

    manage_dependencies($project, $dependency_graph);
}

function migrate(Project $project): void
{
    $config = Config::from_array(composer(JsonFile\to_array($project->root->append('composer.json'))));
    $config->packages_directory = new Filename('vendor');
    $config->import_file = new Filename('vendor/autoload.php');
    $meta = Meta::init();

    $project->config($config);
    $project->meta = $meta;

    $dependency_graph = DependencyGraph::empty();

    if (File\exists($project->root->append('composer.lock'))) {
        Meta::from_array(composer_lock(JsonFile\to_array($project->root->append('composer.lock'))))
            ->packages->each(function (Package $package) use ($project, $dependency_graph, $meta) {
                add_dependency($project, $dependency_graph, $package);
            });
    } else {
        $config->packages->each(function (Package $package) use ($project, $dependency_graph) {
            $package->value->hash = detect_hash($package->value);
            add_dependency($project, $dependency_graph, $package);
        });
    }

    when(Directory\exists($project->packages_directory), fn () => Directory\delete_recursive($project->packages_directory));

    manage_dependencies($project, $dependency_graph);
}
