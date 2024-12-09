<?php

use Phpkg\Application\PackageManager;
use Phpkg\Classes\Package;
use Phpkg\Classes\PackageAlias;
use Phpkg\Classes\Project;
use Phpkg\Exception\PreRequirementsFailedException;
use Phpkg\Git\Repository;
use Phpkg\System;
use PhpRepos\Console\Attributes\Argument;
use PhpRepos\Console\Attributes\Description;
use PhpRepos\Console\Attributes\LongOption;
use function PhpRepos\Cli\Output\line;
use function PhpRepos\Cli\Output\success;
use function PhpRepos\ControlFlow\Conditional\when_exists;
use function PhpRepos\FileManager\Directory\exists;

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
    ?string $version = null,
    #[LongOption('force')]
    #[Description('Use this option to forcefully add the specified package, ignoring version compatibility checks.')]
    ?bool $force = false,
    #[LongOption('project')]
    #[Description('When working in a different directory, provide the relative project path for correct package placement.')]
    string $project = ''
) {
    $environment = System\environment();

    $message = 'Adding package ' . $package_url . ($version ? ' version ' . $version : ' latest version') . '...';
    line($message);

    $project = Project::initialized($environment->pwd->append($project));

    if ($project->config->packages->count() > 0 && ! exists($project->packages_directory)) {
        throw new PreRequirementsFailedException('It seems you didn\'t run the install command. Please make sure you installed your required packages.');
    }

    $project->check_semantic_versioning = ! $force;

    $package_url = when_exists(
        $project->config->aliases->first(fn (PackageAlias $package_alias) => $package_alias->key === $package_url),
        fn (PackageAlias $package_alias) => $package_alias->value,
        fn () => $package_url
    );
    $repository = Repository::from_url($package_url);

    line('Checking installed packages...');

    if ($project->config->packages->has(fn (Package $installed_package) => $installed_package->value->owner === $repository->owner && $installed_package->value->repo === $repository->repo)) {
        throw new PreRequirementsFailedException("Package $package_url is already exists.");
    }

    line('Setting package version...');
    if ($version === PackageManager\DEVELOPMENT_VERSION) {
        $repository->version = $version;
    } else {
        $repository->version = $version
            ? PackageManager\match_highest_version($repository, $version)
            : PackageManager\get_latest_version($repository);
    }

    $package = new Package($package_url, $repository);

    line('Adding the package...');
    $project->config->packages->push($package);

    PackageManager\add($project, $package);

    line('Updating configs...');


    line('Committing configs...');

    PackageManager\commit($project);

    success("Package $package_url has been added successfully.");
};
