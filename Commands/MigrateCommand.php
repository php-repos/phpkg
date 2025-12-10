<?php

use Phpkg\BusinessSpecifications\Project;
use PhpRepos\Console\Attributes\Description;
use PhpRepos\Console\Attributes\LongOption;
use function PhpRepos\Cli\Output\error;
use function PhpRepos\Cli\Output\line;
use function PhpRepos\Cli\Output\success;

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
        return;
    }

    success($outcome->message);
};
