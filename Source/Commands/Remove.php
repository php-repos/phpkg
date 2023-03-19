<?php

namespace Phpkg\Commands\Remove;

use Phpkg\Application\PackageManager;
use Phpkg\Classes\Config\PackageAlias;
use Phpkg\Classes\Config\Library;
use Phpkg\Classes\Environment\Environment;
use Phpkg\Classes\Meta\Dependency;
use Phpkg\Classes\Package\Package;
use Phpkg\Classes\Project\Project;
use Phpkg\Exception\PreRequirementsFailedException;
use Phpkg\Git\Repository;
use PhpRepos\FileManager\File;
use function PhpRepos\Cli\IO\Read\argument;
use function PhpRepos\Cli\IO\Read\parameter;
use function PhpRepos\Cli\IO\Write\line;
use function PhpRepos\Cli\IO\Write\success;
use function PhpRepos\ControlFlow\Conditional\unless;
use function PhpRepos\ControlFlow\Conditional\when_exists;

return function (Environment $environment): void
{
    $package_url = argument(2);
    line('Removing package ' . $package_url);

    $project = new Project($environment->pwd->append(parameter('project', '')));

    if (! File\exists($project->config_file)) {
        throw new PreRequirementsFailedException('Project is not initialized. Please try to initialize using the init command.');
    }

    line('Loading configs...');
    $project = PackageManager\load_config($project);

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
        throw new PreRequirementsFailedException("Package $package_url does not found in your project!");
    }

    line('Loading package\'s config...');
    $project = PackageManager\load_packages($project);

    line('Removing package from config...');
    unless(
        $project->packages->has(fn (Package $package)
            => $package->config->repositories->has(fn (Library $library)
                => $library->repository()->is($dependency->repository()))),
        fn () => PackageManager\remove($project, $dependency)
    );

    $project->config->repositories->forget(fn (Library $installed_library)
        => $installed_library->repository()->is($library->repository()));

    line('Committing configs...');
    PackageManager\commit($project);

    success("Package $package_url has been removed successfully.");
};
