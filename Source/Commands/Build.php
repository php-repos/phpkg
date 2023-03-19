<?php

namespace Phpkg\Commands\Build;

use Phpkg\Application\Builder;
use Phpkg\Application\PackageManager;
use Phpkg\Classes\Build\Build;
use Phpkg\Classes\Environment\Environment;
use Phpkg\Classes\Meta\Dependency;
use Phpkg\Classes\Package\Package;
use Phpkg\Classes\Project\Project;
use Phpkg\Exception\PreRequirementsFailedException;
use PhpRepos\Cli\IO\Write;
use PhpRepos\FileManager\Directory;
use PhpRepos\FileManager\File;
use function PhpRepos\Cli\IO\Read\argument;
use function PhpRepos\Cli\IO\Read\parameter;

return function (Environment $environment): void
{
    Write\line('Start building...');
    $project = new Project($environment->pwd->append(parameter('project', '')));

    if (! File\exists($project->config_file)) {
        throw new PreRequirementsFailedException('Project is not initialized. Please try to initialize using the init command.');
    }

    Write\line('Loading configs...');
    $project = PackageManager\load_config($project);

    Write\line('Checking packages...');
    $packages_installed = $project->meta->dependencies->every(function (Dependency $dependency) use ($project) {
        $package = new Package($project->package_directory($dependency->repository()), $dependency->repository());
        return Directory\exists($package->root);
    });

    if (! $packages_installed) {
        throw new PreRequirementsFailedException('It seems you didn\'t run the install command. Please make sure you installed your required packages.');
    }

    $project = PackageManager\load_packages($project);

    Write\line('Building...');
    $build = new Build($project, argument(2, 'development'));

    Builder\build($project, $build);

    Write\success('Build finished successfully.');
};