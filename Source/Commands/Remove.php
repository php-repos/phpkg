<?php

namespace Phpkg\Commands\Remove;

use Phpkg\Classes\Config\PackageAlias;
use Phpkg\Classes\Config\Config;
use Phpkg\Classes\Config\Library;
use Phpkg\Classes\Environment\Environment;
use Phpkg\Classes\Meta\Meta;
use Phpkg\Classes\Meta\Dependency;
use Phpkg\Classes\Package\Package;
use Phpkg\Classes\Project\Project;
use Phpkg\Git\Repository;
use PhpRepos\FileManager\FileType\Json;
use function PhpRepos\Cli\IO\Read\argument;
use function PhpRepos\Cli\IO\Read\parameter;
use function PhpRepos\Cli\IO\Write\error;
use function PhpRepos\Cli\IO\Write\line;
use function PhpRepos\Cli\IO\Write\success;

function run(Environment $environment): void
{
    $package_url = argument(2);
    line('Removing package ' . $package_url);

    $project = new Project($environment->pwd->subdirectory(parameter('project', '')));

    if (! $project->config_file->exists()) {
        error('Project is not initialized. Please try to initialize using the init command.');
        return;
    }

    line('Loading configs...');
    $project->config(Config::from_array(Json\to_array($project->config_file)));
    $project->meta = Meta::from_array(Json\to_array($project->meta_file));

    $package_url = when_exists(
        $project->config->aliases->first(fn (PackageAlias $package_alias) => $package_alias->alias() === $package_url),
        fn (PackageAlias $package_alias) => $package_alias->package_url(),
        fn () => $package_url
    );
    $repository = Repository::from_url($package_url);

    line('Finding package in configs...');
    $library = $project->config->repositories->first(fn (Library $library) => $library->repository()->is($repository));
    $dependency = $project->meta->dependencies->first(fn (Dependency $dependency) => $dependency->repository()->is($library->repository()));
    if (! $library instanceof Library || ! $dependency instanceof Dependency) {
        error("Package $package_url does not found in your project!");
        return;
    }

    line('Loading package\'s config...');
    $project->meta->dependencies->each(function (Dependency $dependency) use ($project) {
        $package = new Package($project->package_directory($dependency->repository()), $dependency->repository());
        $package->config = $package->config_file->exists() ? Config::from_array(Json\to_array($package->config_file)) : Config::init();
        $project->packages->push($package);
    });

    line('Removing package from config...');
    unless(
        $project->packages->has(fn (Package $package)
            => $package->config->repositories->has(fn (Library $library)
                => $library->repository()->is($dependency->repository()))),
        fn () => remove($project, $dependency)
    );

    $project->config->repositories->forget(fn (Library $installed_library)
        => $installed_library->repository()->is($library->repository()));

    line('Committing configs...');
    Json\write($project->config_file, $project->config->to_array());
    Json\write($project->meta_file, $project->meta->to_array());

    success("Package $package_url has been removed successfully.");
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
        fn () => $package->root->delete_recursive()
            && $project->meta->dependencies->forget(fn (Dependency $meta_dependency)
            => $meta_dependency->repository()->is($dependency->repository()))
    );
}
