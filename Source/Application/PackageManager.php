<?php

namespace Phpkg\Application\PackageManager;

use Phpkg\Classes\Config\Config;
use Phpkg\Classes\Config\Library;
use Phpkg\Classes\Meta\Dependency;
use Phpkg\Classes\Meta\Meta;
use Phpkg\Classes\Package\Package;
use Phpkg\Classes\Project\Project;
use Phpkg\Git\Repository;
use PhpRepos\FileManager\Directory;
use PhpRepos\FileManager\File;
use PhpRepos\FileManager\JsonFile;
use function Phpkg\Providers\GitHub\clone_to;
use function Phpkg\Providers\GitHub\find_latest_commit_hash;
use function Phpkg\Providers\GitHub\find_latest_version;
use function Phpkg\Providers\GitHub\find_version_hash;
use function Phpkg\Providers\GitHub\has_release;
use function Phpkg\System\is_windows;
use function PhpRepos\ControlFlow\Conditional\unless;
use function PhpRepos\ControlFlow\Conditional\when;
use function PhpRepos\ControlFlow\Conditional\when_exists;

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

function download(Repository $repository, string $destination): void
{
    when(
        $repository->version === DEVELOPMENT_VERSION,
        fn () => clone_to($destination, $repository->owner, $repository->repo),
        fn () => \Phpkg\Providers\GitHub\download($destination, $repository->owner, $repository->repo, $repository->version),
    );
}

function add(Project $project, Dependency $dependency): void
{
    $package = new Package($project->package_directory($dependency->repository()), $dependency->repository());

    unless(Directory\exists($package->root), function () use ($project, $package, $dependency) {
        download($package->repository, $package->root);
        $project->meta->dependencies->push($dependency);
    });

    $package->config = File\exists($package->config_file) ? Config::from_array(JsonFile\to_array($package->config_file)) : Config::init();

    $package->config->repositories
        ->except(fn (Library $library)
        => $project->meta->dependencies->has(fn (Dependency $dependency)
        => $dependency->repository()->is($library->repository())))
        ->each(function (Library $library) use ($project) {
            $library->repository()->hash(detect_hash($library->repository()));
            add($project, new Dependency($library->key, $library->meta()));
        });
}

function delete(Project $project, Dependency $dependency): void
{
    $package = $project->packages->take(fn (Package $package) => $package->repository->is($dependency->repository()));

    if (is_null($package)) {
        return;
    }

    $package->config->repositories->each(function (Library $sub_library) use ($project) {
        when_exists(
            $project->meta->dependencies->first(fn (Dependency $dependency)
            => $dependency->repository()->is($sub_library->repository())),
            fn (Dependency $dependency) => delete($project, $dependency)
        );
    });

    delete_package($package);
    $project->meta->dependencies->forget(fn (Dependency $meta_dependency)
    => $meta_dependency->repository()->is($dependency->repository()));
}

function remove(Project $project, Dependency $dependency): void
{
    $package = $project->packages->take(fn (Package $package) => $package->repository->is($dependency->repository()));

    if (is_null($package)) {
        return ;
    }

    $package->config->repositories->each(function (Library $sub_library) use ($project) {
        $dependency = $project->meta->dependencies->first(fn (Dependency $dependency) => $dependency->repository()->is($sub_library->repository()));
        unless(
            $project->config->repositories->has(fn (Library $library) => $library->repository()->is($dependency->repository())),
            fn () => remove($project, $dependency)
        );
    });

    unless(
        $project->packages->has(fn (Package $package)
        => $package->repository->is($dependency->repository())),
        fn () => delete_package($package)
            && $project->meta->dependencies->forget(fn (Dependency $meta_dependency)
            => $meta_dependency->repository()->is($dependency->repository()))
    );
}

function delete_package(Package $package): bool
{
    if (is_windows()) {
        Directory\ls_recursively($package->root)->vertices()->each(fn ($filename) => \chmod($filename, 0777));
    }

    return Directory\delete_recursive($package->root);
}

function install(Project $project): void
{
    Directory\exists_or_create($project->packages_directory);

    $project->meta->dependencies->each(function (Dependency $dependency) use ($project) {
        $package = new Package($project->package_directory($dependency->repository()), $dependency->repository());
        download($package->repository, $package->root);
    });
}

function load_config(Project $project): Project
{
    $project->config(Config::from_array(JsonFile\to_array($project->config_file)));
    $project->meta = File\exists($project->meta_file) ? Meta::from_array(JsonFile\to_array($project->meta_file)) : Meta::init();

    return $project;
}

function load_packages(Project $project): Project
{
    $project->meta->dependencies->each(function (Dependency $dependency) use ($project) {
        $package = new Package($project->package_directory($dependency->repository()), $dependency->repository());
        $package->config = File\exists($package->config_file) ? Config::from_array(JsonFile\to_array($package->config_file)) : Config::init();
        $project->packages->push($package);
    });

    return $project;
}

function commit(Project $project): bool
{
    return JsonFile\write($project->config_file, $project->config->to_array())
        && JsonFile\write($project->meta_file, $project->meta->to_array());
}
