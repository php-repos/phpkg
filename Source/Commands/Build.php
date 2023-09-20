<?php

use Phpkg\Application\Builder;
use Phpkg\Classes\BuildMode;
use Phpkg\Classes\Environment;
use Phpkg\Classes\Project;
use PhpRepos\Cli\Output;
use PhpRepos\Console\Attributes\Argument;
use PhpRepos\Console\Attributes\Description;
use PhpRepos\Console\Attributes\LongOption;

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
    $environment = Environment::setup();

    Output\line('Start building...');
    $project = Project::installed($environment, $environment->pwd->append($project), BuildMode::from($env));

    Output\line('Checking packages...');

    Output\line('Building...');

    Builder\build($project);

    Output\success('Build finished successfully.');
};