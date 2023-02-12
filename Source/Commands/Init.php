<?php

namespace Phpkg\Commands\Init;

use Phpkg\Classes\Config\Config;
use Phpkg\Classes\Meta\Meta;
use Phpkg\Classes\Environment\Environment;
use Phpkg\Classes\Project\Project;
use Phpkg\Exception\PreRequirementsFailedException;
use PhpRepos\FileManager\Directory;
use PhpRepos\FileManager\File;
use PhpRepos\FileManager\Filename;
use PhpRepos\FileManager\JsonFile;
use function PhpRepos\Cli\IO\Read\parameter;
use function PhpRepos\Cli\IO\Write\line;
use function PhpRepos\Cli\IO\Write\success;

function run(Environment $environment): void
{
    line('Init project...');
    $project = new Project($environment->pwd->append(parameter('project', '')));

    if (File\exists($project->config_file)) {
        throw new PreRequirementsFailedException('The project is already initialized.');
    }

    $config = pipe(Config::init(), function (Config $config) {
        $config->packages_directory = new Filename(parameter('packages-directory', $config->packages_directory->string()));

        return $config;
    });

    $meta = Meta::init();

    $project->config($config);
    $project->meta = $meta;

    JsonFile\write($project->config_file, $project->config->to_array());
    JsonFile\write($project->meta_file, $project->meta->to_array());

    Directory\exists_or_create($project->packages_directory);

    success('Project has been initialized.');
}
