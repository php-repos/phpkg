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
use function Phpkg\System\is_windows;
use function PhpRepos\Cli\Output\line;

/**
 * Serves an external project using PHP's built-in server on-the-fly.
 * This command facilitates serving a project on-the-fly by handling the download, build, and serving process using
 * PHP's built-in server. To use this command, provide a valid Git URL (either SSH or HTTPS) as the `package_url`
 * argument. Additionally, you can specify the desired entry point as the optional second argument when the package
 * offers multiple entry points.
 */
return function (
    #[Argument]
    #[Description('The Git repository URL (HTTPS or SSH) of the project you intend to serve.')]
    string $package_url,
    #[Argument]
    #[Description("The entry point you want to execute within the project. If not provided, it will use the first\navailable entry point.")]
    ?string $entry_point = null,
) {
    $environment = System\environment();

    if (is_windows()) {
        line('Unfortunately, the pcntl extension that is required to use the "run" command, is not supported on windows.');
        return;
    }

    line("Serving $package_url on http://localhost:8000");

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

    $command = 'php -S localhost:8000 -t ' . $entry_point_path->parent();

    $process = proc_open($command, [STDIN, STDOUT, STDOUT], $pipes);

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
