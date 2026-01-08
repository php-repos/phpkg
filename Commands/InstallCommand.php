<?php

use Phpkg\Business\Project;
use PhpRepos\Console\Business\Attributes\Description;
use PhpRepos\Console\Business\Attributes\LongOption;
use function Phpkg\Infra\CLI\error;
use function Phpkg\Infra\CLI\line;
use function Phpkg\Infra\CLI\success;

/**
 * Downloads and installs added packages into your project's directory.
 * After cloning the project, you can use the `install` command to have `phpkg` download and populate the packages in
 * your packages directory.
 */
return function (
    #[LongOption('project')]
    #[Description('When working in a different directory, provide the relative project path for correct package placement.')]
    ?string $project = '',
    #[LongOption('force')]
    #[Description('Use this option to forcefully add the specified package, ignoring version compatibility checks.')]
    ?bool $force = false,
) {
    line('Installing packages...');

    $outcome = Project\install($project, $force);

    if (!$outcome->success) {
        error('Failed installing the project. ' . $outcome->message);
        return 1;
    }

    success($outcome->message);
    return 0;
};
