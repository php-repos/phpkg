<?php

use Phpkg\Application\Credentials;
use Phpkg\Application\PackageManager;
use Phpkg\Classes\Dependency;
use Phpkg\Classes\Environment;
use Phpkg\Classes\Package;
use Phpkg\Classes\PackageAlias;
use Phpkg\Classes\Project;
use Phpkg\Exception\PreRequirementsFailedException;
use Phpkg\Git\Repository;
use PhpRepos\Console\Attributes\Argument;
use PhpRepos\Console\Attributes\Description;
use PhpRepos\Console\Attributes\LongOption;
use PhpRepos\FileManager\Directory;
use function PhpRepos\Cli\Output\line;
use function PhpRepos\Cli\Output\success;
use function PhpRepos\ControlFlow\Conditional\unless;
use function PhpRepos\ControlFlow\Conditional\when_exists;

/**
 * Adds the specified package to your project.
 * This command requires a mandatory package argument, which should be a valid git URL (SSH or HTTPS) or a registered
 * alias created using the alias command.
 */
return function(
    #[Argument]
    #[Description("The Git URL (SSH or HTTPS) of the package you want to add. Alternatively, if you have defined an alias for the package, you can use the alias instead.")]
    string $package_url,
    #[Argument]
    #[LongOption('version')]
    #[Description("The version number of the package you want to add. If not provided, the command will add the latest available version.")]
    string $version = null,
    #[LongOption('force')]
    #[Description('Use this option to forcefully add the specified package, ignoring version compatibility checks.')]
    ?bool $force = false,
    #[LongOption('project')]
    #[Description('When working in a different directory, provide the relative project path for correct package placement.')]
    string $project = ''
) {
    $environment = Environment::setup();

    line('Adding package ' . $package_url . ($version ? ' version ' . $version : ' latest version') . '...');

    $project = Project::installed($environment, $environment->pwd->append($project));
    $project->check_semantic_versioning = ! $force;

    line('Setting env credential...');
    Credentials\set_credentials($environment);

    $package_url = when_exists(
        $project->config->aliases->first(fn (PackageAlias $package_alias) => $package_alias->key === $package_url),
        fn (PackageAlias $package_alias) => $package_alias->value,
        fn () => $package_url
    );
    $repository = Repository::from_url($package_url);

    line('Checking installed packages...');
    if ($project->config->packages->has(fn (Dependency $dependency) => $dependency->value->repository->is($repository))) {
        throw new PreRequirementsFailedException("Package $package_url is already exists.");
    }

    line('Setting package version...');
    $repository->version($version ?? PackageManager\get_latest_version($repository));
    $package = new Package($repository);

    unless(Directory\exists($project->packages_directory), fn () => Directory\make_recursive($project->packages_directory));

    line('Detecting version hash...');
    $package->repository->hash(PackageManager\detect_hash($package->repository));

    line('Adding the package...');
    $dependency = new Dependency($package_url, $package);
    PackageManager\add($project, $dependency);

    line('Updating configs...');
    $project->config->packages->push($dependency);

    line('Committing configs...');

    PackageManager\commit($project);

    success("Package $package_url has been added successfully.");
};
