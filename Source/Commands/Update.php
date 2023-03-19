<?php

namespace Phpkg\Commands\Update;

use Phpkg\Application\Credentials;
use Phpkg\Application\PackageManager;
use Phpkg\Classes\Config\PackageAlias;
use Phpkg\Classes\Config\Library;
use Phpkg\Classes\Environment\Environment;
use Phpkg\Classes\Meta\Dependency;
use Phpkg\Classes\Package\Package;
use Phpkg\Classes\Project\Project;
use Phpkg\Exception\PreRequirementsFailedException;
use Phpkg\Git\Repository;
use PhpRepos\FileManager\Directory;
use PhpRepos\FileManager\File;
use function PhpRepos\Cli\IO\Read\parameter;
use function PhpRepos\Cli\IO\Read\argument;
use function PhpRepos\Cli\IO\Write\line;
use function PhpRepos\Cli\IO\Write\success;
use function PhpRepos\ControlFlow\Conditional\when_exists;

return function (Environment $environment): void
{
    $package_url = argument(2);
    $version = parameter('version');

    line('Updating package ' . $package_url . ' to ' . ($version ? 'version ' . $version : 'latest version') . '...');

    $project = new Project($environment->pwd->append(parameter('project', '')));

    if (! File\exists($project->config_file)) {
        throw new PreRequirementsFailedException('Project is not initialized. Please try to initialize using the init command.');
    }

    line('Setting env credential...');
    Credentials\set_credentials($environment);

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
    $dependency = when_exists($library, fn (Library $library)
        => $project->meta->dependencies->first(fn (Dependency $dependency)
            => $dependency->repository()->is($library->repository())));

    if (! $library instanceof Library || ! $dependency instanceof Dependency) {
        throw new PreRequirementsFailedException("Package $package_url does not found in your project!");
    }

    line('Setting package version...');
    $library->repository()->version($version ?? PackageManager\get_latest_version($library->repository()));

    line('Loading package\'s config...');
    $packages_installed = $project->meta->dependencies->every(function (Dependency $dependency) use ($project) {
        $package = new Package($project->package_directory($dependency->repository()), $dependency->repository());
        return Directory\exists($package->root);
    });

    if (! $packages_installed) {
        throw new PreRequirementsFailedException('It seems you didn\'t run the install command. Please make sure you installed your required packages.');
    }

    $project = PackageManager\load_packages($project);

    line('Deleting package\'s files...');
    PackageManager\delete($project, $dependency);

    line('Detecting version hash...');
    $library->repository()->hash(PackageManager\detect_hash($library->repository()));

    line('Downloading the package with new version...');
    $dependency = new Dependency($package_url, $library->meta());
    PackageManager\add($project, $dependency);

    line('Updating configs...');
    $project->config->repositories->push($library);

    line('Committing new configs...');
    PackageManager\commit($project);

    success("Package $package_url has been updated.");
};
