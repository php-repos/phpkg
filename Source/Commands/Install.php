<?php

use Phpkg\Application\Credentials;
use Phpkg\Application\PackageManager;
use Phpkg\Classes\Environment\Environment;
use Phpkg\Classes\Project\Project;
use Phpkg\Exception\PreRequirementsFailedException;
use PhpRepos\Console\Attributes\Description;
use PhpRepos\Console\Attributes\LongOption;
use PhpRepos\FileManager\File;
use function PhpRepos\Cli\IO\Write\line;
use function PhpRepos\Cli\IO\Write\success;

/**
 * Downloads and installs added packages into your project's directory.
 * After cloning the project, you can use the `install` command to have `phpkg` download and populate the packages in
 * your packages directory.
 */
return function (
    #[LongOption('project')]
    #[Description('When working in a different directory, provide the relative project path for correct package placement.')]
    ?string $project = '',
) {
    $environment = Environment::for_project();

    line('Installing packages...');

    $project = new Project($environment->pwd->append($project));

    if (! File\exists($project->config_file)) {
        throw new PreRequirementsFailedException('Project is not initialized. Please try to initialize using the init command.');
    }

    line('Setting env credential...');
    Credentials\set_credentials($environment);

    line('Loading configs...');
    $project = PackageManager\load_config($project);

    line('Downloading packages...');
    PackageManager\install($project);

    success('Packages has been installed successfully.');
};
