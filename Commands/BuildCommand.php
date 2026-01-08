<?php

use Phpkg\Business\Project;
use PhpRepos\Console\Business\Attributes\Description;
use PhpRepos\Console\Business\Attributes\LongOption;
use function Phpkg\Infra\CLI\error;
use function Phpkg\Infra\CLI\line;
use function Phpkg\Infra\CLI\success;

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
