<?php

namespace Phpkg\Commands\Run;

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
use function PhpRepos\ControlFlow\Conditional\unless;

return function (Environment $environment): void
{
    $package_url = argument(2);

    Credentials\set_credentials($environment);

    $repository = Repository::from_url($package_url);
    $repository->version(PackageManager\get_latest_version($repository));
    $repository->hash(PackageManager\detect_hash($repository));

    $root = Path::from_string(sys_get_temp_dir())->append('phpkg/runner/' . $repository->owner . '/' . $repository->repo . '/' . $repository->version);

    unless(Directory\exists($root), fn () => PackageManager\download($repository, $root));

    $project = new Project($root);

    $project = PackageManager\load_config($project);

    PackageManager\install($project);

    PackageManager\load_packages($project);

    $build = new Build($project, 'production');

    Builder\build($project, $build);

    $entry_point = argument(3) ? argument(3) : $project->config->entry_points->first();

    $entry_point_path = $build->root()->append($entry_point);

    if (! File\exists($entry_point_path)) {
        throw new PreRequirementsFailedException("Entry point $entry_point is not defined in the package.");
    }

    $process = proc_open('php ' . $entry_point_path->string(), [STDIN, STDOUT, STDOUT], $pipes);
    proc_close($process);
};
