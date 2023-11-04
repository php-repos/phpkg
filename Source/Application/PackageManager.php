<?php

namespace Phpkg\Application\PackageManager;

use Exception;
use Phpkg\Classes\Dependency;
use Phpkg\Classes\LinkPair;
use Phpkg\Classes\NamespaceFilePair;
use Phpkg\Classes\Package;
use Phpkg\Classes\PackageAlias;
use Phpkg\Classes\Project;
use Phpkg\Exception\PreRequirementsFailedException;
use Phpkg\Git\Repository;
use PhpRepos\FileManager\Directory;
use PhpRepos\FileManager\Filename;
use PhpRepos\FileManager\JsonFile;
use PhpRepos\FileManager\Path;
use function Phpkg\Git\Version\compare;
use function Phpkg\Git\Version\has_major_change;
use function Phpkg\Git\Version\is_semantic;
use function Phpkg\Providers\GitHub\download;
use function Phpkg\Providers\GitHub\find_latest_commit_hash;
use function Phpkg\Providers\GitHub\find_latest_version;
use function Phpkg\Providers\GitHub\find_version_hash;
use function Phpkg\Providers\GitHub\has_release;
use function Phpkg\System\is_windows;
use function PhpRepos\ControlFlow\Conditional\unless;
use function PhpRepos\ControlFlow\Conditional\when;

const DEVELOPMENT_VERSION = 'development';

function get_latest_version(Repository $repository): string
{
    return has_release($repository->owner, $repository->repo)
        ? find_latest_version($repository->owner, $repository->repo)
        : DEVELOPMENT_VERSION;
}

function detect_hash(Repository $repository): string
{
    return $repository->version !== DEVELOPMENT_VERSION
        ? find_version_hash($repository->owner, $repository->repo, $repository->version)
        : find_latest_commit_hash($repository->owner, $repository->repo);
}

function cache_path(Project $project, Repository $repository): Path
{
    return $project->environment->temp->append("installer/$repository->domain/$repository->owner/$repository->repo/$repository->hash");
}

function package_path(Project $project, Repository $repository): Path
{
    return $project->packages_directory->append("$repository->owner/$repository->repo");
}

function build_package_path(Project $project, Repository $repository): Path
{
    return build_packages_directory($project)->append("$repository->owner/$repository->repo");
}

function build_root(Project $project): Path
{
    return $project->root->append('builds')->append($project->build_mode->value);
}

function build_packages_directory(Project $project): Path
{
    return build_root($project)->append($project->config->packages_directory);
}

function import_file_path(Project $project): Path
{
    return build_root($project)->append($project->config->import_file);
}

/**
 * @throws PreRequirementsFailedException
 */
function download_and_resolve_dependencies(Project $project, Dependency $dependency): bool
{
    unless(Directory\exists($project->environment->temp), fn () => Directory\make_recursive($project->environment->temp));

    /** @var Dependency|null $installed_dependency */
    $installed_dependency = $project->dependencies->first(fn (Dependency $installed_dependency) => $installed_dependency->value->repository->is($dependency->value->repository));

    if ($installed_dependency) {
        if (is_semantic($installed_dependency->value->repository->version) && is_semantic($dependency->value->repository->version)) {
            when(
                $project->check_semantic_versioning && has_major_change($installed_dependency->value->repository->version, $dependency->value->repository->version),
                fn () => throw new PreRequirementsFailedException('There is a major upgrade in the version number. Make sure it is a compatible change and if it is, try updating by --force.')
            );

            if (compare($installed_dependency->value->repository->version, $dependency->value->repository->version) >= 0) {
                $installed_dependency->value->config->packages
                    ->each(function (Dependency $sub_dependency) use ($project) {
                        if (! $project->dependencies->has(fn(Dependency $installed_dependency) => $installed_dependency->value->repository->is($sub_dependency->value->repository))) {
                            $sub_dependency->value->repository->hash(detect_hash($sub_dependency->value->repository));
                            download_and_resolve_dependencies($project, $sub_dependency);
                        }
                    });

                return true;
            }

            $project->dependencies->forget(fn (Dependency $installed_dependency) => $installed_dependency->value->repository->is($dependency->value->repository));
        }

        $project->meta->dependencies->forget(fn (Dependency $installed_dependency) => $installed_dependency->value->repository->is($dependency->value->repository));
        $dependency = $dependency->value(new Package($dependency->value->repository));
    }

    $root = cache_path($project, $dependency->value->repository);
    unless(Directory\exists($root), fn () => Directory\make_recursive($root)
        && download($root, $dependency->value->repository->owner, $dependency->value->repository->repo, $dependency->value->repository->hash));

    $dependency->value->install($root);
    $project->dependencies->push($dependency);
    $dependency->value->config->packages->each(fn (Dependency $sub_dependency) =>
        $sub_dependency->value->repository->hash(detect_hash($sub_dependency->value->repository))
        && download_and_resolve_dependencies($project, $sub_dependency));

    return true;
}

/**
 * @throws PreRequirementsFailedException
 */
function add(Project $project, Dependency $dependency): void
{
    download_and_resolve_dependencies($project, $dependency);

    $project->dependencies->each(function (Dependency $installed_dependency) use ($project) {
        if (Directory\exists(cache_path($project, $installed_dependency->value->repository))) {
            Directory\renew_recursive(package_path($project, $installed_dependency->value->repository));
            Directory\preserve_copy_recursively(
                cache_path($project, $installed_dependency->value->repository),
                package_path($project, $installed_dependency->value->repository)
            );
            $project->meta->dependencies->push($installed_dependency);
        }
    });

    Directory\ls_all($project->packages_directory)
        ->each(function (Path $owner_directory) use ($project) {
            $owner = $owner_directory->leaf();
            Directory\ls_all($owner_directory)
                ->each(function (Path $repo_directory) use ($project, $owner) {
                    $repo = $repo_directory->leaf();
                    unless(
                        $project->dependencies->has(fn (Dependency $installed_dependency)
                            => $installed_dependency->value->repository->owner === $owner->string() && $installed_dependency->value->repository->repo === $repo->string()),
                        fn () => Directory\delete_recursive($repo_directory)
                            && $project->meta->dependencies->forget(fn (Dependency $installed_dependency)
                                => $installed_dependency->value->repository->owner === $owner->string() && $installed_dependency->value->repository->repo === $repo->string())
                    );
                });
        });
}

function remove(Project $project, Dependency $dependency): void
{
    $project->dependencies->forget(fn (Dependency $installed_dependency)
        => $installed_dependency->value->repository->is($dependency->value->repository));

    $dependency->value->config->packages->each(function (Dependency $sub_dependency) use ($project) {
        /** @var Dependency|null $sub_dependency */
        $sub_dependency = $project->dependencies->first(fn (Dependency $installed_dependency) => $installed_dependency->value->repository->is($sub_dependency->value->repository));

        if ($sub_dependency) {
            remove($project, $sub_dependency);
        }
    });

    unless(
        // Project defined the package as required
        $project->config->packages->has(fn (Dependency $required_dependency) => $required_dependency->value->repository->is($dependency->value->repository))
            // Package is required in other packages
        || $project->dependencies->has(function (Dependency $other_dependency) use ($dependency) {
            return $other_dependency->value->config->packages->has(function (Dependency $sub_dependency) use ($dependency) {
                return $sub_dependency->value->repository->is($dependency->value->repository);
            });
        }),
        fn () => delete_package($dependency->value) && $project->meta->dependencies->forget(fn (Dependency $installed_dependency) => $installed_dependency->value->repository->is($dependency->value->repository)),
        fn () => $project->dependencies->push($dependency),
    );
}

function delete_package(Package $package): bool
{
    if (is_windows()) {
        Directory\ls_recursively($package->root)->vertices()->each(fn ($filename) => \chmod($filename, 0777));
    }

    return Directory\delete_recursive($package->root);
}

/**
 * @throws Exception
 */
function install(Project $project): void
{
    Directory\exists_or_create($project->packages_directory);

    $project->meta->dependencies->each(function (Dependency $dependency) use ($project) {
        $root = package_path($project, $dependency->value->repository);
        if (! Directory\exists($root)) {
            Directory\make_recursive($root);
        }
        download($root, $dependency->value->repository->owner, $dependency->value->repository->repo, $dependency->value->repository->hash);
    });
}

function update(Project $project, Dependency $dependency): void
{
    download_and_resolve_dependencies($project, $dependency);

    $project->dependencies->each(function (Dependency $installed_dependency) use ($project) {
        if (Directory\exists(cache_path($project, $installed_dependency->value->repository))) {
            Directory\renew_recursive(package_path($project, $installed_dependency->value->repository));
            Directory\preserve_copy_recursively(
                cache_path($project, $installed_dependency->value->repository),
                package_path($project, $installed_dependency->value->repository)
            );
            $project->meta->dependencies->push($installed_dependency);
        }
    });

    Directory\ls_all($project->packages_directory)
        ->each(function (Path $owner_directory) use ($project) {
            $owner = $owner_directory->leaf();
            Directory\ls_all($owner_directory)
                ->each(function (Path $repo_directory) use ($project, $owner) {
                    $repo = $repo_directory->leaf();
                    unless(
                        $project->dependencies->has(fn (Dependency $installed_dependency)
                        => $installed_dependency->value->repository->owner === $owner->string() && $installed_dependency->value->repository->repo === $repo->string()),
                        fn () => Directory\delete_recursive($repo_directory)
                            && $project->meta->dependencies->forget(fn (Dependency $installed_dependency)
                            => $installed_dependency->value->repository->owner === $owner->string() && $installed_dependency->value->repository->repo === $repo->string())
                    );
                });
        });
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
    $project->config->packages->each(function (Dependency $dependency) use (&$config) {
        $config['packages'][$dependency->key] = $dependency->value->repository->version;
    });
    $project->config->aliases->each(function (PackageAlias $package_alias) use (&$config) {
        $config['aliases'][$package_alias->key] = $package_alias->value;
    });

    $meta = $project->meta->dependencies->reduce(function (array $packages, Dependency $dependency) {
        $packages['packages'][$dependency->key] = [
            'owner' => $dependency->value->repository->owner,
            'repo' => $dependency->value->repository->repo,
            'version' => $dependency->value->repository->version,
            'hash' => $dependency->value->repository->hash,
        ];

        return $packages;
    }, ['packages' => []]);

    return JsonFile\write($project->root->append(Project::CONFIG_FILENAME), $config)
        && JsonFile\write($project->root->append(Project::META_FILENAME), $meta);
}
