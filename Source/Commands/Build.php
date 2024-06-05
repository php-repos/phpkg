<?php

use Phpkg\Application\Builder;
use Phpkg\Classes\BuildMode;
use Phpkg\Classes\Project;
use Phpkg\Exception\PreRequirementsFailedException;
use Phpkg\System;
use PhpRepos\Cli\Output;
use PhpRepos\Console\Attributes\Argument;
use PhpRepos\Console\Attributes\Description;
use PhpRepos\Console\Attributes\LongOption;
use function PhpRepos\FileManager\Directory\exists;

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
    $environment = System\environment();

    Output\line('Start building...');
    $project = Project::initialized($environment->pwd->append($project));

    if ($project->config->packages->count() > 0 && ! exists($project->packages_directory)) {
        throw new PreRequirementsFailedException('It seems you didn\'t run the install command. Please make sure you installed your required packages.');
    }

    $project->build_mode = BuildMode::from($env);

    Output\line('Checking packages...');

    Output\line('Building...');

    Builder\build($project);

    Output\success('Build finished successfully.');
};