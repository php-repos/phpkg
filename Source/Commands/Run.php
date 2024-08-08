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
use PhpRepos\Console\Attributes\ExcessiveArguments;
use PhpRepos\Console\Attributes\LongOption;
use PhpRepos\FileManager\Directory;
use PhpRepos\FileManager\File;
use PhpRepos\FileManager\Path;
use function Phpkg\Git\Repositories\download_archive;
use function PhpRepos\Datatype\Str\after_first_occurrence;

/**
 * Runs a project on-the-fly.
 * This command seamlessly manages the downloading, building, and running of the specified project.
 *
 * To use this command, provide a valid Git URL (either SSH or HTTPS) as the `url_or_path` argument.
 * For local projects, use an absolute or relative path from your current working directory.
 *
 * If the project has multiple entry points, you can specify the desired entry point as an optional second argument.
 */
return function (
    #[Argument]
    #[Description("URL or path to the package.\nThe Git repository URL (HTTPS or SSH) of the package you want to run. In case you want to run a local package, pass an absolute path to the package, or a relative path from your current working directory.")]
    string $url_or_path,
    #[Argument]
    #[Description("The entry point you want to execute within the package. If not provided, it will use the first\navailable entry point.")]
    ?string $entry_point = null,
    #[LongOption('version')]
    #[Description("Specify the version of the project to run.\nTo run a specific version, use the `version` option. To run a version based on a specific commit hash, use `development#{commit-hash}`.")]
    ?string $version = null,
    #[Description("Version number that you want to use for this project to run.")]
    #[ExcessiveArguments]
    array $entry_point_arguments = []
) {
    $environment = System\environment();

    if (str_starts_with($url_or_path, 'https://') || str_starts_with($url_or_path, 'http://')) {
        $repository = Repository::from_url($url_or_path);

        if ($version && str_starts_with($version, PackageManager\DEVELOPMENT_VERSION . '#')) {
            $repository->version = PackageManager\DEVELOPMENT_VERSION;
            $repository->hash = after_first_occurrence($version, PackageManager\DEVELOPMENT_VERSION . '#');
        } else {
            $repository->version = $version && $version !== PackageManager\DEVELOPMENT_VERSION ? PackageManager\match_highest_version($repository, $version) : PackageManager\DEVELOPMENT_VERSION;
            $repository->hash = PackageManager\detect_hash($repository);
        }

        $root = $environment->temp->append('runner/github.com/' . $repository->owner . '/' . $repository->repo . '/' . $repository->hash);

        if (! Directory\exists($root)) {
            Directory\make_recursive($root) && download_archive($repository, $root);

            $project = new Project($root);
            $composer_file = $project->root->append('composer.json');

            if (! File\exists($project->config_file) && File\exists($composer_file)) {
                PackageManager\migrate($project);
                PackageManager\commit($project);
            }

            $project = Project::initialized($root);
            PackageManager\install($project);
        }
    } else {
        $root = str_starts_with($url_or_path, '/') ? Path::from_string($url_or_path) : $environment->pwd->append($url_or_path);
    }

    $project = Project::initialized($root);
    $project->build_mode = BuildMode::Production;

    Builder\build($project);

    $entry_point = $entry_point ?: $project->config->entry_points->first();

    $entry_point_path = Builder\build_root($project)->append($entry_point);

    if (! File\exists($entry_point_path)) {
        throw new PreRequirementsFailedException("Entry point $entry_point is not defined in the package.");
    }

    $process = proc_open('php ' . $entry_point_path->string() . ' ' . implode(' ', $entry_point_arguments), [STDIN, STDOUT, STDOUT], $pipes);
    proc_close($process);
};
