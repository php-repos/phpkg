<?php

namespace Phpkg\Commands\Serve;

use Phpkg\Classes\Build\Build;
use Phpkg\Classes\Config\Config;
use Phpkg\Classes\Config\LinkPair;
use Phpkg\Classes\Environment\Environment;
use Phpkg\Classes\Meta\Dependency;
use Phpkg\Classes\Meta\Meta;
use Phpkg\Classes\Package\Package;
use Phpkg\Classes\Project\Project;
use Phpkg\Exception\PreRequirementsFailedException;
use Phpkg\Git\Repository;
use Phpkg\PackageManager;
use PhpRepos\FileManager\Directory;
use PhpRepos\FileManager\File;
use PhpRepos\FileManager\Filename;
use PhpRepos\FileManager\JsonFile;
use PhpRepos\FileManager\Path;
use function Phpkg\Commands\Build\add_autoloads;
use function Phpkg\Commands\Build\add_executables;
use function Phpkg\Commands\Build\compile_packages;
use function Phpkg\Commands\Build\compile_project_files;
use function PhpRepos\Cli\IO\Read\argument;
use function PhpRepos\Cli\IO\Write\line;

function run(Environment $environment): void
{
    $package_url = argument(2);

    line("Serving $package_url on http://localhost:8000");

    set_credentials($environment);

    $project = init_project($package_url);
    install_packages($project);
    $build = prepare_build($project);
    build($project, $build);

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
}

function init_project(string $package_url): Project
{
    $repository = Repository::from_url($package_url);
    $repository->version(PackageManager\get_latest_version($repository));
    $repository->hash(PackageManager\detect_hash($repository));

    $root = Path::from_string(sys_get_temp_dir())->append('phpkg/runner/' . $repository->owner . '/' . $repository->repo . '/' . $repository->version);

    unless(Directory\exists($root), fn () => PackageManager\download($repository, $root));

    $project = new Project($root);

    $project->config(Config::from_array(JsonFile\to_array($project->config_file)));
    $project->meta = Meta::from_array(JsonFile\to_array($project->meta_file));

    Directory\exists_or_create($project->packages_directory);

    return $project;
}

function install_packages(Project $project)
{
    $project->meta->dependencies->each(function (Dependency $dependency) use ($project) {
        $package = new Package($project->package_directory($dependency->repository()), $dependency->repository());
        unless(Directory\exists($package->root), fn () => PackageManager\download($package->repository, $package->root));
        $package->config = File\exists($package->config_file) ? Config::from_array(JsonFile\to_array($package->config_file)) : Config::init();
        $project->packages->push($package);
    });
}

function prepare_build(Project $project): Build
{
    $build = new Build($project, 'production');
    Directory\renew_recursive($build->root());
    Directory\exists_or_create($build->packages_directory());
    $build->load_namespace_map();

    return $build;
}

function build(Project $project, Build $build): void
{
    $project->packages->each(function (Package $package) use ($project, $build) {
        compile_packages($package, $build);
    });

    compile_project_files($build);

    $project->config->entry_points->each(function (Filename $entry_point) use ($build) {
        add_autoloads($build, $build->root()->append($entry_point));
    });

    $project->packages->each(function (Package $package)  use ($project, $build) {
        $package->config->executables->each(function (LinkPair $executable) use ($build, $package) {
            add_executables($build, $build->package_root($package)->append($executable->source()), $build->root()->append($executable->symlink()));
        });
    });
}
