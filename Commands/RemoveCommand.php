<?php

use Phpkg\Business\Package;
use PhpRepos\Console\Attributes\Argument;
use PhpRepos\Console\Attributes\Description;
use PhpRepos\Console\Attributes\LongOption;
use function PhpRepos\Cli\Output\error;
use function PhpRepos\Cli\Output\line;
use function PhpRepos\Cli\Output\success;

/**
 * Removes the specified package from your project.
 * This command requires a mandatory package argument, which should be a valid git URL (SSH or HTTPS) or a registered
 * alias created using the alias command.
 */
return function (
    #[Argument]
    #[Description("The Git URL (SSH or HTTPS) of the package you wish to remove. Alternatively, if you have previously\n defined an alias for the package using the alias command, you can use the alias instead.")]
    string $package_url,
    #[LongOption('project')]
    #[Description('When working in a different directory, provide the relative project path for correct package placement.')]
    string $project = ''
) {
    line('Removing package ' . $package_url);

    $outcome = Package\remove($project, $package_url);

    if (!$outcome->success) {
        error($outcome->message);
        return 1;
    }

    success($outcome->message);
    return 0;
};
