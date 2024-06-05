<?php

use Phpkg\Classes\BuildMode;
use Phpkg\Classes\Project;
use Phpkg\System;
use PhpRepos\Console\Attributes\Description;
use PhpRepos\Console\Attributes\LongOption;
use function Phpkg\Application\Builder\build_root;
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
    $environment = System\environment();

    $project = Project::initialized($environment->pwd->append($project));
    $project->build_mode = BuildMode::Development;

    renew_recursive(build_root($project));

    $project->build_mode = BuildMode::Production;
    renew_recursive(build_root($project));

    success('Build directory has been flushed.');
};
