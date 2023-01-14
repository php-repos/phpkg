<?php

namespace Phpkg\Commands\Run;

use Phpkg\Classes\Build\Build;
use Phpkg\Classes\Config\Config;
use Phpkg\Classes\Config\LinkPair;
use Phpkg\Classes\Environment\Environment;
use Phpkg\Classes\Meta\Dependency;
use Phpkg\Classes\Meta\Meta;
use Phpkg\Classes\Package\Package;
use Phpkg\Classes\Project\Project;
use Phpkg\Git\Repository;
use PhpRepos\FileManager\Filesystem\Filename;
use PhpRepos\FileManager\FileType\Json;
use PhpRepos\FileManager\Filesystem\Directory;
use function Phpkg\Commands\Build\add_autoloads;
use function Phpkg\Commands\Build\add_executables;
use function Phpkg\Commands\Build\compile_packages;
use function Phpkg\Commands\Build\compile_project_files;
use function PhpRepos\Cli\IO\Read\argument;
use function PhpRepos\Cli\IO\Write\error;

function run(Environment $environment): void
{
    $package_url = argument(2);

    set_credentials($environment);

    $repository = Repository::from_url($package_url)->latest_version()->detect_hash();

    $root = Directory::from_string(sys_get_temp_dir())->subdirectory('phpkg/runner/' . $repository->owner . '/' . $repository->repo);

    $repository->download($root);

    $project = new Project($root);

    $project->config($project->config_file->exists() ? Config::from_array(Json\to_array($project->config_file)) : Config::init());
    $project->meta = Meta::from_array(Json\to_array($project->meta_file));

    $project->packages_directory->exists_or_create();

    $project->meta->dependencies->each(function (Dependency $dependency) use ($project) {
        $package = new Package($project->package_directory($dependency->repository()), $dependency->repository());
        $package->download();
        $package->config = $package->config_file->exists() ? Config::from_array(Json\to_array($package->config_file)) : Config::init();
        $project->packages->push($package);
    });

    $build = new Build($project, 'production');
    $build->root()->renew_recursive();
    $build->packages_directory()->exists_or_create();
    $build->load_namespace_map();

    $project->packages->each(function (Package $package) use ($project, $build) {
        compile_packages($package, $build);
    });

    compile_project_files($build);

    $project->config->entry_points->each(function (Filename $entry_point) use ($build) {
        add_autoloads($build, $build->root()->file($entry_point));
    });

    $project->packages->each(function (Package $package)  use ($project, $build) {
        $package->config->executables->each(function (LinkPair $executable) use ($build, $package) {
            add_executables($build, $build->package_root($package)->file($executable->source()), $build->root()->symlink($executable->symlink()));
        });
    });

    $entry_point = argument(3) ? argument(3) : $project->config->entry_points->first();

    $entry_point_path = $build->root()->file($entry_point)->path;

    if (! $entry_point_path->exists()) {
        error("Entry point $entry_point is not defined in the package.");
        return;
    }

    $process = proc_open('php ' . $entry_point_path->string(), [STDIN, STDOUT, STDOUT], $pipes);
    proc_close($process);
}
