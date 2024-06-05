<?php

use Phpkg\Application\PackageManager;
use Phpkg\Classes\Project;
use Phpkg\System;
use PhpRepos\Console\Attributes\Description;
use PhpRepos\Console\Attributes\LongOption;
use function PhpRepos\Cli\Output\line;
use function PhpRepos\Cli\Output\success;

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
    $environment = System\environment();

    line('Installing packages...');

    $project = Project::initialized($environment->pwd->append($project));

    line('Downloading packages...');
    PackageManager\install($project);

    success('Packages has been installed successfully.');
};
