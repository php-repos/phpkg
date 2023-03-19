<?php

namespace Phpkg\Commands\Serve;

use Phpkg\Application\Builder;
use Phpkg\Application\Credentials;
use Phpkg\Application\PackageManager;
use Phpkg\Classes\Build\Build;
use Phpkg\Classes\Environment\Environment;
use Phpkg\Classes\Project\Project;
use Phpkg\Exception\PreRequirementsFailedException;
use Phpkg\Git\Repository;
use PhpRepos\FileManager\Directory;
use PhpRepos\FileManager\File;
use PhpRepos\FileManager\Path;
use function PhpRepos\Cli\IO\Read\argument;
use function PhpRepos\Cli\IO\Write\line;
use function PhpRepos\ControlFlow\Conditional\unless;

return function (Environment $environment): void
{
    $package_url = argument(2);

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

    $entry_point = argument(3) ? argument(3) : $project->config->entry_points->first();

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
