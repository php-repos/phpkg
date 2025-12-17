<?php

use Phpkg\Business\Project;
use PhpRepos\Console\Attributes\Description;
use PhpRepos\Console\Attributes\LongOption;
use function PhpRepos\Cli\Output\error;
use function PhpRepos\Cli\Output\line;
use function PhpRepos\Cli\Output\success;

/**
 * Removes the build directory and temp directory used for caching downloaded packages.
 * This command helps clean up generated files and cached data.
 */
return function (
    #[LongOption('project')]
    #[Description('When working in a different directory, provide the relative project path for correct package placement.')]
    ?string $project = '',
) {
    line('Flushing build and temp directories...');

    $outcome = Project\flush($project);

    if (!$outcome->success) {
        error($outcome->message);
        return 1;
    }

    success($outcome->message);

    return 0;
};
