<?php

use Phpkg\Application\PackageManager;
use Phpkg\Classes\Project;
use Phpkg\Exception\PreRequirementsFailedException;
use Phpkg\System;
use PhpRepos\Cli\Output;
use PhpRepos\Console\Attributes\Description;
use PhpRepos\Console\Attributes\LongOption;
use PhpRepos\FileManager\File;

/**
 * The `migrate` command is used to migrate from a Composer project to a `phpkg` project.
 * make sure you have the `composer.json` file available in the project.
 */
return function (
    #[LongOption('project')]
    #[Description('When working in a different directory, provide the relative project path for correct package placement.')]
    ?string $project = '',
) {
    $environment = System\environment();

    $project = new Project($environment->pwd->append($project));

    $composer_file = $project->root->append('composer.json');

    if (! File\exists($composer_file)) {
        throw new PreRequirementsFailedException('There is no composer.json file.');
    }

    PackageManager\migrate($project);

    PackageManager\commit($project);

    Output\success('Composer package has been successfully migrated to a phpkg project. Enjoy.');
};
