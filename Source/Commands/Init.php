<?php

use Phpkg\Application\PackageManager;
use Phpkg\Classes\Config;
use Phpkg\Classes\Meta;
use Phpkg\Classes\Project;
use Phpkg\Exception\PreRequirementsFailedException;
use Phpkg\System;
use PhpRepos\Console\Attributes\Description;
use PhpRepos\Console\Attributes\LongOption;
use PhpRepos\FileManager\Directory;
use PhpRepos\FileManager\File;
use PhpRepos\FileManager\Filename;
use function PhpRepos\Cli\Output\line;
use function PhpRepos\Cli\Output\success;
use function PhpRepos\ControlFlow\Transformation\pipe;

/**
 * This command initializes the project by adding the necessary files and directories.
 * You have the option to specify a `packages-directory`. If provided, your packages will be placed within the specified
 * directory instead of the default `Packages` directory
 */
return function(
    #[LongOption('project')]
    #[Description('When working in a different directory, provide the relative project path for correct package placement.')]
    string $project = '',
    #[LongOption('packages-directory')]
    #[Description("Specify a custom directory where `phpkg` will save your libraries or packages. This allows you to\n structure your project with a different directory name instead of the default `Packages` directory.")]
    ?string $packages_directory = null,
) {
    $environment = System\environment();
    $project = new Project($environment->pwd->append($project));

    line('Init project...');
    if (File\exists($project->config_file)) {
        throw new PreRequirementsFailedException('The project is already initialized.');
    }

    $config = pipe(Config::init(), function (Config $config) use ($packages_directory) {
        $packages_directory = $packages_directory ?: $config->packages_directory->string();
        $config->packages_directory = new Filename($packages_directory);

        return $config;
    });

    $project->config($config);
    $project->meta = Meta::init();

    PackageManager\commit($project);

    Directory\exists_or_create($project->packages_directory);

    success('Project has been initialized.');
};
