<?php

use Phpkg\Application\Migrator;
use Phpkg\Application\PackageManager;
use Phpkg\Classes\Config\Config;
use Phpkg\Classes\Meta\Meta;
use Phpkg\Classes\Environment\Environment;
use Phpkg\Classes\Project\Project;
use Phpkg\Exception\PreRequirementsFailedException;
use PhpRepos\Cli\IO\Write;
use PhpRepos\Console\Attributes\Description;
use PhpRepos\Console\Attributes\LongOption;
use PhpRepos\FileManager\Filename;
use PhpRepos\FileManager\File;
use PhpRepos\FileManager\JsonFile;

/**
 * The `migrate` command is used to migrate from a Composer project to a `phpkg` project.
 */
return function (
    #[LongOption('project')]
    #[Description('When working in a different directory, provide the relative project path for correct package placement.')]
    ?string $project = '',
) {
    $environment = Environment::for_project();

    $project = new Project($environment->pwd->append($project));

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
