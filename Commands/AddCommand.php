<?php

use Phpkg\Business\Package;
use PhpRepos\Console\Business\Attributes\Argument;
use PhpRepos\Console\Business\Attributes\Description;
use PhpRepos\Console\Business\Attributes\LongOption;
use function Phpkg\Infra\CLI\error;
use function Phpkg\Infra\CLI\line;
use function Phpkg\Infra\CLI\success;

/**
 * Adds the specified package to your project.
 * This command requires a mandatory package argument, which should be a valid git URL (SSH or HTTPS) or a registered
 * alias created using the alias command.
 */
return function(
    #[Argument]
    #[Description("The Git URL (SSH or HTTPS) of the package you want to add. Alternatively, if you have defined an alias for the package, you can use the alias instead.")]
    string $package_url,
    #[Argument]
    #[LongOption('version')]
    #[Description("The version number of the package you want to add. If not provided, the command will add the latest available version.")]
    ?string $version = null,
    #[LongOption('force')]
    #[Description('Use this option to forcefully add the specified package, ignoring version compatibility checks.')]
    ?bool $force = false,
    #[LongOption('project')]
    #[Description('When working in a different directory, provide the relative project path for correct package placement.')]
    string $project = ''
) {
    $message = 'Adding package ' . $package_url . ($version ? ' version ' . $version : ' latest version') . '...';
    line($message);

    $outcome = Package\add($project, $package_url, $version, $force);

    if (!$outcome->success) {
        error($outcome->message);
        return 1;
    }

    success($outcome->message);
    return 0;
};
