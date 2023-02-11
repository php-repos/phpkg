<?php

namespace Phpkg\Commands\Install;

use Phpkg\Classes\Config\Config;
use Phpkg\Classes\Environment\Environment;
use Phpkg\Classes\Meta\Meta;
use Phpkg\Classes\Meta\Dependency;
use Phpkg\Classes\Package\Package;
use Phpkg\Classes\Project\Project;
use Phpkg\Exception\PreRequirementsFailedException;
use PhpRepos\FileManager\Directory;
use PhpRepos\FileManager\File;
use PhpRepos\FileManager\JsonFile;
use function PhpRepos\Cli\IO\Read\parameter;
use function PhpRepos\Cli\IO\Write\line;
use function PhpRepos\Cli\IO\Write\success;

function run(Environment $environment): void
{
    line('Installing packages...');

    $project = new Project($environment->pwd->append(parameter('project', '')));

    if (! File\exists($project->config_file)) {
        throw new PreRequirementsFailedException('Project is not initialized. Please try to initialize using the init command.');
    }

    line('Setting env credential...');
    set_credentials($environment);

    line('Loading configs...');
    $project->config(Config::from_array(JsonFile\to_array($project->config_file)));
    $project->meta = Meta::from_array(JsonFile\to_array($project->meta_file));

    Directory\exists_or_create($project->packages_directory);

    line('Downloading packages...');
    $project->meta->dependencies->each(function (Dependency $dependency) use ($project) {
        $package = new Package($project->package_directory($dependency->repository()), $dependency->repository());
        line('Downloading package ' . $dependency->key . ' to ' . $package->root);
        $package->repository->download($package->root);
    });

    success('Packages has been installed successfully.');
}
