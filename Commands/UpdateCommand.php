<?php

use Phpkg\Business\Package;
use PhpRepos\Console\Attributes\Argument;
use PhpRepos\Console\Attributes\Description;
use PhpRepos\Console\Attributes\LongOption;
use function PhpRepos\Cli\Output\error;
use function PhpRepos\Cli\Output\line;
use function PhpRepos\Cli\Output\success;

/**
 * Allows you to update the version of a specified package in your PHP project.
 * If you need to obtain the latest version of an added package, this command can be used. It requires a mandatory
 * `package_url` argument, which should be a valid Git URL (SSH or HTTPS) pointing to the desired package, or an alias
 * registered using the `alias` command. Additionally, you have the option to pass an `--version` option. If provided,
 * `phpkg` will download the exact specified version; otherwise, it will fetch the latest available version.
 */
return function (
    #[Argument]
    #[Description("The Git URL (SSH or HTTPS) of the package you want to update. Alternatively, if you have defined an alias for the package, you can use the alias instead.")]
    string $package_url,
    #[Argument]
    #[LongOption('version')]
    #[Description("The version number of the package you want to update to. If not provided, the command will update to the latest available version.")]
    ?string $version = null,
    #[LongOption('force')]
    #[Description('Use this option to forcefully update the specified package, ignoring version compatibility checks.')]
    ?bool $force = false,
    #[LongOption('project')]
    #[Description('When working in a different directory, provide the relative project path for correct package placement.')]
    ?string $project = ''
) {
    line('Updating package ' . $package_url . ' to ' . ($version ? 'version ' . $version : 'latest version') . '...');

    $outcome = Package\update($project, $package_url, $version, $force);

    if (!$outcome->success) {
        error($outcome->message);
        return;
    }

    success($outcome->message);
};
