<?php

use Phpkg\Business\Project;
use PhpRepos\Console\Attributes\Description;
use PhpRepos\Console\Attributes\LongOption;
use function PhpRepos\Cli\Output\error;
use function PhpRepos\Cli\Output\line;
use function PhpRepos\Cli\Output\success;

/**
 * This command initializes the project by adding the necessary files and directories.
 * You have the option to specify a `packages-directory`. If provided, your packages will be placed within the specified
 * directory instead of the default `Packages` directory
 */
return function(
    #[LongOption('project')]
    #[Description('When working in a different directory, provide the relative project path for correct project placement.')]
    string $project = '',
    #[LongOption('packages-directory')]
    #[Description("Specify a custom directory where `phpkg` will save your libraries or packages. This allows you to\n structure your project with a different directory name instead of the default `Packages` directory.")]
    ?string $packages_directory = null,
) {
    line('Init project...');

    $outcome = Project\init($project, $packages_directory);

    if (!$outcome->success) {
        error('Failed to initialize project! ' . $outcome->message);
        return;
    }

    success($outcome->message);
};
