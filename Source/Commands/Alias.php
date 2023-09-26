<?php

use Phpkg\Application\PackageManager;
use Phpkg\Classes\Environment;
use Phpkg\Classes\PackageAlias;
use Phpkg\Classes\Project;
use Phpkg\Exception\PreRequirementsFailedException;
use PhpRepos\Console\Attributes\Argument;
use PhpRepos\Console\Attributes\Description;
use PhpRepos\Console\Attributes\LongOption;
use function PhpRepos\Cli\Output\line;
use function PhpRepos\Cli\Output\success;

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
    $environment = Environment::setup();

    line("Registering alias $alias for $package_url...");

    $project = Project::installed($environment, $environment->pwd->append($project));

    $registered_alias = $project->config->aliases->first(fn (PackageAlias $package_alias) => $package_alias->key === $alias);

    if ($registered_alias) {
        throw new PreRequirementsFailedException("The alias is already registered for $registered_alias->value.");
    }

    $project->config->aliases->push(new PackageAlias($alias, $package_url));

    PackageManager\commit($project);

    success("Alias $alias has been registered for $package_url.");
};
