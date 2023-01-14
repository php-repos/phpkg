<?php

namespace Phpkg\Commands\Alias;

use Phpkg\Classes\Config\PackageAlias;
use Phpkg\Classes\Config\Config;
use Phpkg\Classes\Environment\Environment;
use Phpkg\Classes\Project\Project;
use PhpRepos\FileManager\FileType\Json;
use function PhpRepos\Cli\IO\Read\argument;
use function PhpRepos\Cli\IO\Read\parameter;
use function PhpRepos\Cli\IO\Write\error;
use function PhpRepos\Cli\IO\Write\line;
use function PhpRepos\Cli\IO\Write\success;

function run(Environment $environment): void
{
    $alias = argument(2);
    $package_url = argument(3);

    line("Registering alias $alias for $package_url...");

    $project = new Project($environment->pwd->subdirectory(parameter('project', '')));

    if (! $project->config_file->exists()) {
        error('Project is not initialized. Please try to initialize using the init command.');
        return;
    }

    $project->config(Config::from_array(Json\to_array($project->config_file)));

    $registered_alias = $project->config->aliases->first(fn (PackageAlias $package_alias) => $package_alias->alias() === $alias);

    if ($registered_alias) {
        error("The alias is already registered for $registered_alias->value.");
        return;
    }

    $project->config->aliases->push(new PackageAlias($alias, $package_url));

    Json\write($project->config_file, $project->config->to_array());

    success("Alias $alias has been registered for $package_url.");
}
