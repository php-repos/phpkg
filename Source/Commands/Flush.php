<?php

use Phpkg\Classes\Build\Build;
use Phpkg\Classes\Environment\Environment;
use Phpkg\Classes\Project\Project;
use PhpRepos\Console\Attributes\Description;
use PhpRepos\Console\Attributes\LongOption;
use function PhpRepos\Cli\IO\Write\success;
use function PhpRepos\FileManager\Directory\renew_recursive;

/**
 * If you need to remove any built files, running this command will create a fresh builds directory.
 */
return function (
    #[LongOption('project')]
    #[Description('When working in a different directory, provide the relative project path for correct package placement.')]
    ?string $project = '',
) {
    $environment = Environment::for_project();

    $project = new Project($environment->pwd->append($project));

    $development_build = new Build($project, 'development');
    $production_build = new Build($project, 'production');

    renew_recursive($development_build->root());
    renew_recursive($production_build->root());

    success('Build directory has been flushed.');
};
