<?php

use Phpkg\Application\Builder;
use Phpkg\Application\PackageManager;
use Phpkg\Classes\Build\Build;
use Phpkg\Classes\Environment\Environment;
use Phpkg\Classes\Meta\Dependency;
use Phpkg\Classes\Package\Package;
use Phpkg\Classes\Project\Project;
use Phpkg\Exception\PreRequirementsFailedException;
use PhpRepos\Cli\IO\Write;
use PhpRepos\Console\Attributes\Argument;
use PhpRepos\Console\Attributes\Description;
use PhpRepos\Console\Attributes\LongOption;
use PhpRepos\FileManager\Directory;
use PhpRepos\FileManager\File;

/**
 * Compiles and adds project files to the build directory.
 * Builds the project and places the resulting files in the build directory within the designated environment's folder,
 * typically named `build`. By default, the environment is set to `development`. If you wish to build the production
 * environment, you can specify the environment argument as `production`.
 */
return function (
    #[Argument]
    #[Description('The environment for which you want to build your project. If not provided, the default is `development`')]
    ?string $env = 'development',
    #[LongOption('project')]
    #[Description('When working in a different directory, provide the relative project path for correct package placement.')]
    ?string $project = '',
) {
    $environment = Environment::for_project();

    Write\line('Start building...');
    $project = new Project($environment->pwd->append($project));

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
    $build = new Build($project, $env);

    Builder\build($project, $build);

    Write\success('Build finished successfully.');
};