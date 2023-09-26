<?php

use Phpkg\Classes\BuildMode;
use Phpkg\Classes\Environment;
use Phpkg\Classes\Project;
use PhpRepos\Console\Attributes\Description;
use PhpRepos\Console\Attributes\LongOption;
use function Phpkg\Application\PackageManager\build_root;
use function PhpRepos\Cli\Output\success;
use function PhpRepos\FileManager\Directory\renew_recursive;

/**
 * If you need to remove any built files, running this command will create a fresh builds directory.
 */
return function (
    #[LongOption('project')]
    #[Description('When working in a different directory, provide the relative project path for correct package placement.')]
    ?string $project = '',
) {
    $environment = Environment::setup();

    renew_recursive(build_root(Project::installed($environment, $environment->pwd->append($project))));
    renew_recursive(build_root(Project::installed($environment, $environment->pwd->append($project), BuildMode::Production)));

    success('Build directory has been flushed.');
};
