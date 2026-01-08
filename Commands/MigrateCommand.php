<?php

use Phpkg\Business\Project;
use PhpRepos\Console\Business\Attributes\Description;
use PhpRepos\Console\Business\Attributes\LongOption;
use function Phpkg\Infra\CLI\error;
use function Phpkg\Infra\CLI\line;
use function Phpkg\Infra\CLI\success;

/**
 * The `migrate` command is used to migrate from a Composer project to a `phpkg` project.
 * make sure you have the `composer.json` file available in the project.
 */
return function (
    #[LongOption('project')]
    #[Description('When working in a different directory, provide the relative project path for correct package placement.')]
    ?string $project = '',
) {
    line('Migrating Composer project to phpkg project...');

    $outcome = Project\migrate($project);

    if (!$outcome->success) {
        error($outcome->message);
        return 1;
    }

    success($outcome->message);
    return 0;
};
