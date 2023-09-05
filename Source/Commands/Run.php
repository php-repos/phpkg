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
use function PhpRepos\ControlFlow\Conditional\unless;

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
    $environment = Environment::for_project();

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

    $entry_point = $entry_point ?: $project->config->entry_points->first();

    $entry_point_path = $build->root()->append($entry_point);

    if (! File\exists($entry_point_path)) {
        throw new PreRequirementsFailedException("Entry point $entry_point is not defined in the package.");
    }

    $process = proc_open('php ' . $entry_point_path->string(), [STDIN, STDOUT, STDOUT], $pipes);
    proc_close($process);
};
