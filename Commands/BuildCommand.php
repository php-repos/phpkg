<?php

use Phpkg\BusinessSpecifications\Project;
use PhpRepos\Console\Attributes\Description;
use PhpRepos\Console\Attributes\LongOption;
use function PhpRepos\Cli\Output\error;
use function PhpRepos\Cli\Output\line;
use function PhpRepos\Cli\Output\success;

/**
 * Compiles and adds project files to the build directory.
 * Builds the project and places the resulting files in the `build` directory.
 */
return function (
    #[LongOption('project')]
    #[Description('When working in a different directory, provide the relative project path for correct package placement.')]
    ?string $project = '',
) {
    line('Start building...');

    $outcome = Project\build($project);

    if (!$outcome->success) {
        error($outcome->message);
        return 1;
    }

    success($outcome->message);

    return 0;
};
