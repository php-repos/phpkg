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
use function Phpkg\System\is_windows;
use function PhpRepos\Cli\Output\line;
use function PhpRepos\Datatype\Str\after_first_occurrence;

/**
 * Serves an external project using PHP's built-in server on-the-fly.
 * This command seamlessly manages the downloading, building, and serving of the specified project using PHP built-in server.
 *
 * To use this command, provide a valid Git URL (either SSH or HTTPS) as the `url_or_path` argument.
 * For local projects, use an absolute or relative path from your current working directory.
 *
 * If the project has multiple entry points, you can specify the desired entry point as an optional second argument.
 */
return function (
    #[Argument]
    #[Description("URL or path to the package.\nThe Git repository URL (HTTPS or SSH) of the package you want to serve. In case you want to serve a local package, pass an absolute path to the package, or a relative path from your current working directory.")]
    string $url_or_path,
    #[Argument]
    #[Description("The entry point you want to execute within the project. If not provided, it will use the first\navailable entry point.")]
    ?string $entry_point = null,
    #[LongOption('version')]
    #[Description("Specify the version of the project to serve.\nTo serve a specific version, use the `version` option. To serve a version based on a specific commit hash, use `development#{commit-hash}`.")]
    ?string $version = null,
    #[ExcessiveArguments]
    array $entry_point_arguments = []
) {
    $environment = System\environment();

    if (is_windows()) {
        line('Unfortunately, the pcntl extension that is required to use the "run" command, is not supported on windows.');
        return;
    }

    line("Serving $url_or_path on http://localhost:8000");

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

            // TODO: Find a composer package to be able to test this section, currently there is no test for this section.
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

    $command = 'php -S localhost:8000 -t ' . $entry_point_path->parent();

    $process = proc_open($command . ' ' . implode(' ', $entry_point_arguments), [STDIN, STDOUT, STDOUT], $pipes);

    $terminate_server = function ($signal) use ($process) {
        $pid = proc_get_status($process)['pid'];
        posix_kill($pid, $signal);
        exit();
    };

    pcntl_signal(SIGTERM, $terminate_server);
    pcntl_signal(SIGINT, $terminate_server);

    while (true) {
        pcntl_signal_dispatch();
        usleep(100);
    }
};
