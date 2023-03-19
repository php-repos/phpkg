<?php

namespace Phpkg\Commands\Alias;

use Phpkg\Application\PackageManager;
use Phpkg\Classes\Config\PackageAlias;
use Phpkg\Classes\Environment\Environment;
use Phpkg\Classes\Project\Project;
use Phpkg\Exception\PreRequirementsFailedException;
use PhpRepos\FileManager\File;
use function PhpRepos\Cli\IO\Read\argument;
use function PhpRepos\Cli\IO\Read\parameter;
use function PhpRepos\Cli\IO\Write\line;
use function PhpRepos\Cli\IO\Write\success;

return function (Environment $environment): void
{
    $alias = argument(2);
    $package_url = argument(3);

    line("Registering alias $alias for $package_url...");

    $project = new Project($environment->pwd->append(parameter('project', '')));

    if (! File\exists($project->config_file)) {
        throw new PreRequirementsFailedException('Project is not initialized. Please try to initialize using the init command.');
    }

    $project = PackageManager\load_config($project);

    $registered_alias = $project->config->aliases->first(fn (PackageAlias $package_alias) => $package_alias->alias() === $alias);

    if ($registered_alias) {
        throw new PreRequirementsFailedException("The alias is already registered for $registered_alias->value.");
    }

    $project->config->aliases->push(new PackageAlias($alias, $package_url));

    PackageManager\commit($project);

    success("Alias $alias has been registered for $package_url.");
};
