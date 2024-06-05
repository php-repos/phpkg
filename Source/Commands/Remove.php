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
 * Removes the specified package from your project.
 * This command requires a mandatory package argument, which should be a valid git URL (SSH or HTTPS) or a registered
 * alias created using the alias command.
 */
return function (
    #[Argument]
    #[Description("The Git URL (SSH or HTTPS) of the package you wish to remove. Alternatively, if you have previously\n defined an alias for the package using the alias command, you can use the alias instead.")]
    string $package_url,
    #[LongOption('project')]
    #[Description('When working in a different directory, provide the relative project path for correct package placement.')]
    string $project = ''
) {
    $environment = System\environment();

    line('Removing package ' . $package_url);

    $project = Project::initialized($environment->pwd->append($project));

    if ($project->config->packages->count() > 0 && ! exists($project->packages_directory)) {
        throw new PreRequirementsFailedException('It seems you didn\'t run the install command. Please make sure you installed your required packages.');
    }

    $package_url = when_exists(
        $project->config->aliases->first(fn (PackageAlias $package_alias) => $package_alias->key === $package_url),
        fn (PackageAlias $package_alias) => $package_alias->value,
        fn () => $package_url
    );
    $repository = Repository::from_url($package_url);

    line('Finding package in configs...');
    if (! $project->config->packages->has(fn (Package $installed_package) => $installed_package->value->owner === $repository->owner && $installed_package->value->repo === $repository->repo)) {
        throw new PreRequirementsFailedException("Package $package_url does not found in your project!");
    }

    line('Removing package from config...');
    $project->config->packages->forget(fn (Package $installed_package) => $installed_package->value->owner === $repository->owner && $installed_package->value->repo === $repository->repo);

    $package = $project->meta->packages->first(fn (Package $installed_package) => $installed_package->value->owner === $repository->owner && $installed_package->value->repo === $repository->repo);
    PackageManager\remove($project, $package);

    line('Committing configs...');
    PackageManager\commit($project);

    success("Package $package_url has been removed successfully.");
};
