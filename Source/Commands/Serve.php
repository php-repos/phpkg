<?php

use Phpkg\Application\Builder;
use Phpkg\Application\Credentials;
use Phpkg\Application\PackageManager;
use Phpkg\Classes\Build\Build;
use Phpkg\Classes\Environment\Environment;
use Phpkg\Classes\Project\Project;
use Phpkg\Exception\PreRequirementsFailedException;
use Phpkg\Git\Repository;
use PhpRepos\Console\Attributes\Argument;
use PhpRepos\Console\Attributes\Description;
use PhpRepos\FileManager\Directory;
use PhpRepos\FileManager\File;
use PhpRepos\FileManager\Path;
use function Phpkg\System\is_windows;
use function PhpRepos\Cli\IO\Write\line;
use function PhpRepos\ControlFlow\Conditional\unless;

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
    $environment = Environment::for_project();

    if (is_windows()) {
        line('Unfortunately, the pcntl extension that is required to use the "run" command, is not supported on windows.');
        return;
    }

    line("Serving $package_url on http://localhost:8000");

    Credentials\set_credentials($environment);

    $repository = Repository::from_url($package_url);
    $repository->version(PackageManager\get_latest_version($repository));
    $repository->hash(PackageManager\detect_hash($repository));

    $root = Path::from_string(sys_get_temp_dir())->append('phpkg/runner/' . $repository->owner . '/' . $repository->repo . '/' . $repository->version);

    unless(Directory\exists($root), fn () => PackageManager\download($repository, $root));

    $project = new Project($root);

    $project = PackageManager\load_config($project);

    PackageManager\install($project);

    $project = PackageManager\load_packages($project);

    $build = new Build($project, 'production');

    Builder\build($project, $build);

    $entry_point = $entry_point ?: $project->config->entry_points->first();

    $entry_point_path = $build->root()->append($entry_point);

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
