<?php

namespace Phpkg\Commands\Migrate;

use Phpkg\Application\Migrator;
use Phpkg\Application\PackageManager;
use Phpkg\Classes\Config\Config;
use Phpkg\Classes\Meta\Meta;
use Phpkg\Classes\Environment\Environment;
use Phpkg\Classes\Project\Project;
use Phpkg\Exception\PreRequirementsFailedException;
use PhpRepos\Cli\IO\Write;
use PhpRepos\FileManager\Filename;
use PhpRepos\FileManager\File;
use PhpRepos\FileManager\JsonFile;
use function PhpRepos\Cli\IO\Read\parameter;

return function (Environment $environment): void
{
    $project = new Project($environment->pwd->append(parameter('project', '')));

    $composer_file = $project->root->append('composer.json');
    $composer_lock_file = $project->root->append('composer.lock');

    if (! File\exists($composer_file)) {
        throw new PreRequirementsFailedException('There is no composer.json file.');
    }

    if (! File\exists($composer_lock_file)) {
        throw new PreRequirementsFailedException('There is no composer.lock file.');
    }

    $composer_setting = JsonFile\to_array($composer_file);
    $composer_lock_setting = JsonFile\to_array($composer_lock_file);

    $config = Config::init();
    $config->packages_directory = new Filename('vendor');
    $meta = Meta::init();

    $project->config($config);
    $project->meta = $meta;

    Migrator\migrate($project, $composer_setting, $composer_lock_setting);

    PackageManager\commit($project);

    Write\success('Migration has been finished successfully.');
};
