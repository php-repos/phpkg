<?php

namespace Phpkg\Commands\Update;

use Phpkg\Classes\Config\PackageAlias;
use Phpkg\Classes\Config\Config;
use Phpkg\Classes\Config\Library;
use Phpkg\Classes\Meta\Meta;
use Phpkg\Classes\Environment\Environment;
use Phpkg\Classes\Meta\Dependency;
use Phpkg\Classes\Package\Package;
use Phpkg\Classes\Project\Project;
use Phpkg\Exception\PreRequirementsFailedException;
use Phpkg\Git\Repository;
use PhpRepos\FileManager\FileType\Json;
use function Phpkg\Commands\Add\add;
use function PhpRepos\Cli\IO\Read\parameter;
use function PhpRepos\Cli\IO\Read\argument;
use function PhpRepos\Cli\IO\Write\error;
use function PhpRepos\Cli\IO\Write\line;
use function PhpRepos\Cli\IO\Write\success;

function run(Environment $environment): void
{
    $package_url = argument(2);
    $version = parameter('version');

    line('Updating package ' . $package_url . ' to ' . ($version ? 'version ' . $version : 'latest version') . '...');

    $project = new Project($environment->pwd->subdirectory(parameter('project', '')));

    if (! $project->config_file->exists()) {
        throw new PreRequirementsFailedException('Project is not initialized. Please try to initialize using the init command.');
    }

    line('Setting env credential...');
    set_credentials($environment);

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
    $dependency = when_exists($library, fn (Library $library)
        => $project->meta->dependencies->first(fn (Dependency $dependency)
            => $dependency->repository()->is($library->repository())));

    if (! $library instanceof Library || ! $dependency instanceof Dependency) {
        throw new PreRequirementsFailedException("Package $package_url does not found in your project!");
    }

    line('Setting package version...');
    $version ? $library->repository()->version($version) : $library->repository()->latest_version();

    line('Loading package\'s config...');
    $packages_installed = $project->meta->dependencies->every(function (Dependency $dependency) use ($project) {
        $package = new Package($project->package_directory($dependency->repository()), $dependency->repository());
        $package->config = $package->config_file->exists() ? Config::from_array(Json\to_array($package->config_file)) : Config::init();
        $project->packages->push($package);
        return $package->is_downloaded();
    });

    if (! $packages_installed) {
        throw new PreRequirementsFailedException('It seems you didn\'t run the install command. Please make sure you installed your required packages.');
    }

    line('Deleting package\'s files...');
    delete($project, $dependency);

    line('Detecting version hash...');
    $library->repository()->detect_hash();

    line('Downloading the package with new version...');
    $dependency = new Dependency($package_url, $library->meta());
    add($project, $dependency);

    line('Updating configs...');
    $project->config->repositories->push($library);

    line('Committing new configs...');
    Json\write($project->config_file, $project->config->to_array());
    Json\write($project->meta_file, $project->meta->to_array());

    success("Package $package_url has been updated.");
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

    $package->root->delete_recursive();
    $project->meta->dependencies->forget(fn (Dependency $meta_dependency)
        => $meta_dependency->repository()->is($dependency->repository()));
}
