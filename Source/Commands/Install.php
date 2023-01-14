<?php

namespace Phpkg\Commands\Install;

use Phpkg\Classes\Config\Config;
use Phpkg\Classes\Environment\Environment;
use Phpkg\Classes\Meta\Meta;
use Phpkg\Classes\Meta\Dependency;
use Phpkg\Classes\Package\Package;
use Phpkg\Classes\Project\Project;
use PhpRepos\FileManager\FileType\Json;
use function PhpRepos\Cli\IO\Read\parameter;
use function PhpRepos\Cli\IO\Write\error;
use function PhpRepos\Cli\IO\Write\line;
use function PhpRepos\Cli\IO\Write\success;

function run(Environment $environment): void
{
    line('Installing packages...');

    $project = new Project($environment->pwd->subdirectory(parameter('project', '')));

    if (! $project->config_file->exists()) {
        error('Project is not initialized. Please try to initialize using the init command.');
        return;
    }

    line('Setting env credential...');
    set_credentials($environment);

    line('Loading configs...');
    $project->config(Config::from_array(Json\to_array($project->config_file)));
    $project->meta = Meta::from_array(Json\to_array($project->meta_file));

    $project->packages_directory->exists_or_create();

    line('Downloading packages...');
    $project->meta->dependencies->each(function (Dependency $dependency) use ($project) {
        $package = new Package($project->package_directory($dependency->repository()), $dependency->repository());
        line('Downloading package ' . $dependency->key . ' to ' . $package->root);
        $package->download();
    });

    success('Packages has been installed successfully.');
}
