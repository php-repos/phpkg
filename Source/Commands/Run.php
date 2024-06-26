<?php

use Phpkg\Application\Builder;
use Phpkg\Application\PackageManager;
use Phpkg\Classes\BuildMode;
use Phpkg\Classes\Project;
use Phpkg\Exception\PreRequirementsFailedException;
use Phpkg\Git\Repository;
use Phpkg\System;
use PhpRepos\Console\Attributes\Argument;
use PhpRepos\Console\Attributes\Description;
use PhpRepos\FileManager\Directory;
use PhpRepos\FileManager\File;
use function Phpkg\Git\Repositories\download_archive;

/**
 * Allows you to execute a project on-the-fly.
 * It seamlessly handles the process of downloading, building, and running the specified package. To use this command,
 * provide a valid Git URL (either SSH or HTTPS) as the `package_url` argument. If the package offers multiple
 * entry points, you can specify the desired entry point as the optional second argument.
 */
return function (
    #[Argument]
    #[Description('The Git repository URL (HTTPS or SSH) of the project you want to run.')]
    string $package_url,
    #[Argument]
    #[Description("The entry point you want to execute within the project. If not provided, it will use the first\navailable entry point.")]
    ?string $entry_point = null
) {
    $environment = System\environment();

    $repository = Repository::from_url($package_url);
    $repository->version = PackageManager\get_latest_version($repository);
    $repository->hash = PackageManager\detect_hash($repository);

    $root = $environment->temp->append('runner/github.com/' . $repository->owner . '/' . $repository->repo . '/' . $repository->hash);

    if (! Directory\exists($root)) {
        Directory\make_recursive($root) && download_archive($repository, $root);
        $project = Project::initialized($root);
        PackageManager\install($project);
    }

    $project = Project::initialized($root);
    $project->build_mode = BuildMode::Production;

    Builder\build($project);

    $entry_point = $entry_point ?: $project->config->entry_points->first();

    $entry_point_path = Builder\build_root($project)->append($entry_point);

    if (! File\exists($entry_point_path)) {
        throw new PreRequirementsFailedException("Entry point $entry_point is not defined in the package.");
    }

    $process = proc_open('php ' . $entry_point_path->string(), [STDIN, STDOUT, STDOUT], $pipes);
    proc_close($process);
};
