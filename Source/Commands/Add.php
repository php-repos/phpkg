<?php

namespace Phpkg\Commands\Add;

use Phpkg\Classes\Config\PackageAlias;
use Phpkg\Classes\Config\Config;
use Phpkg\Classes\Config\Library;
use Phpkg\Classes\Environment\Environment;
use Phpkg\Classes\Meta\Meta;
use Phpkg\Classes\Meta\Dependency;
use Phpkg\Classes\Package\Package;
use Phpkg\Classes\Project\Project;
use Phpkg\Exception\PreRequirementsFailedException;
use Phpkg\Git\Repository;
use Phpkg\PackageManager;
use PhpRepos\FileManager\Directory;
use PhpRepos\FileManager\File;
use PhpRepos\FileManager\JsonFile;
use function PhpRepos\Cli\IO\Read\parameter;
use function PhpRepos\Cli\IO\Read\argument;
use function PhpRepos\Cli\IO\Write\line;
use function PhpRepos\Cli\IO\Write\success;

function run(Environment $environment): void
{
    $package_url = argument(2);
    $version = parameter('version');

    line('Adding package ' . $package_url . ($version ? ' version ' . $version : ' latest version') . '...');

    $project = new Project($environment->pwd->append(parameter('project', '')));

    if (! File\exists($project->config_file)) {
        throw new PreRequirementsFailedException('Project is not initialized. Please try to initialize using the init command.');
    }

    line('Setting env credential...');
    set_credentials($environment);

    line('Loading configs...');
    $project->config(Config::from_array(JsonFile\to_array($project->config_file)));
    $project->meta = File\exists($project->meta_file) ? Meta::from_array(JsonFile\to_array($project->meta_file)) : Meta::init();

    $package_url = when_exists(
        $project->config->aliases->first(fn (PackageAlias $package_alias) => $package_alias->alias() === $package_url),
        fn (PackageAlias $package_alias) => $package_alias->package_url(),
        fn () => $package_url
    );
    $repository = Repository::from_url($package_url);

    line('Checking installed packages...');
    if ($project->config->repositories->has(fn (Library $library) => $library->repository()->is($repository))) {
        throw new PreRequirementsFailedException("Package $package_url is already exists.");
    }

    line('Setting package version...');
    $repository->version($version ?? PackageManager\get_latest_version($repository));
    $library = new Library($package_url, $repository);

    line('Creating package directory...');
    unless(Directory\exists($project->packages_directory), fn () => Directory\make_recursive($project->packages_directory));

    line('Detecting version hash...');
    $library->repository()->hash(PackageManager\detect_hash($library->repository()));

    line('Downloading the package...');
    $dependency = new Dependency($package_url, $library->meta());
    add($project, $dependency);

    line('Updating configs...');
    $project->config->repositories->push($library);

    line('Committing configs...');
    JsonFile\write($project->config_file, $project->config->to_array());
    JsonFile\write($project->meta_file, $project->meta->to_array());

    success("Package $package_url has been added successfully.");
}

function add(Project $project, Dependency $dependency): void
{
    $package = new Package($project->package_directory($dependency->repository()), $dependency->repository());

    unless(Directory\exists($package->root), fn () => PackageManager\download($package->repository, $package->root) && $project->meta->dependencies->push($dependency));

    $package->config = File\exists($package->config_file) ? Config::from_array(JsonFile\to_array($package->config_file)) : Config::init();

    $package->config->repositories
        ->except(fn (Library $library)
            => $project->meta->dependencies->has(fn (Dependency $dependency)
                => $dependency->repository()->is($library->repository())))
        ->each(function (Library $library) use ($project) {
            $library->repository()->hash(PackageManager\detect_hash($library->repository()));
            add($project, new Dependency($library->key, $library->meta()));
        });
}
