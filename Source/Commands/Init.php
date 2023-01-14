<?php

namespace Phpkg\Commands\Init;

use Phpkg\Classes\Config\Config;
use Phpkg\Classes\Meta\Meta;
use Phpkg\Classes\Environment\Environment;
use Phpkg\Classes\Project\Project;
use PhpRepos\FileManager\Filesystem\Filename;
use PhpRepos\FileManager\FileType\Json;
use function PhpRepos\Cli\IO\Read\parameter;
use function PhpRepos\Cli\IO\Write\error;
use function PhpRepos\Cli\IO\Write\line;
use function PhpRepos\Cli\IO\Write\success;

function run(Environment $environment): void
{
    line('Init project...');
    $project = new Project($environment->pwd->subdirectory(parameter('project', '')));

    if ($project->config_file->exists()) {
        error('The project is already initialized.');
        return;
    }

    $config = pipe(Config::init(), function (Config $config) {
        $config->packages_directory = new Filename(parameter('packages-directory', $config->packages_directory->string()));

        return $config;
    });

    $meta = Meta::init();

    $project->config($config);
    $project->meta = $meta;

    Json\write($project->config_file, $project->config->to_array());
    Json\write($project->meta_file, $project->meta->to_array());

    $project->packages_directory->exists_or_create();

    success('Project has been initialized.');
}
