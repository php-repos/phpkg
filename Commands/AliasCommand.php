<?php

use Phpkg\Business\Package;
use PhpRepos\Console\Business\Attributes\Argument;
use PhpRepos\Console\Business\Attributes\Description;
use PhpRepos\Console\Business\Attributes\LongOption;
use function Phpkg\Infra\CLI\error;
use function Phpkg\Infra\CLI\line;
use function Phpkg\Infra\CLI\success;

/**
 * Defines the provided alias for a given package, allowing you to use the alias in other commands where a package URL is required.
 */
return function (
    #[Argument]
    #[Description('The desired alias that you want to create for the package.')]
    string $alias,
    #[Argument]
    #[Description('The Git URL (SSH or HTTPS) of the package you intend to associate with the alias.')]
    string $package_url,
    #[LongOption('project')]
    #[Description('When working in a different directory, provide the relative project path for correct package placement.')]
    ?string $project = '',
) {
    line("Registering alias $alias for $package_url...");

    $outcome = Package\register_alias($project, $alias, $package_url);

    if (!$outcome->success) {
        error($outcome->message);
        return 1;
    }

    success($outcome->message);
    return 0;
};
