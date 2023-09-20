<?php

use Phpkg\Application\PackageManager;
use Phpkg\Classes\Dependency;
use Phpkg\Classes\Environment;
use Phpkg\Classes\PackageAlias;
use Phpkg\Classes\Project;
use Phpkg\Exception\PreRequirementsFailedException;
use Phpkg\Git\Repository;
use PhpRepos\Console\Attributes\Argument;
use PhpRepos\Console\Attributes\Description;
use PhpRepos\Console\Attributes\LongOption;
use function PhpRepos\Cli\Output\line;
use function PhpRepos\Cli\Output\success;
use function PhpRepos\ControlFlow\Conditional\when_exists;

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
    $environment = Environment::setup();

    line('Removing package ' . $package_url);

    $project = Project::installed($environment, $environment->pwd->append($project));

    $package_url = when_exists(
        $project->config->aliases->first(fn (PackageAlias $package_alias) => $package_alias->key === $package_url),
        fn (PackageAlias $package_alias) => $package_alias->value,
        fn () => $package_url
    );
    $repository = Repository::from_url($package_url);

    line('Finding package in configs...');
    if (! $project->config->packages->first(fn (Dependency $dependency) => $dependency->value->repository->is($repository))) {
        throw new PreRequirementsFailedException("Package $package_url does not found in your project!");
    }

    line('Removing package from config...');
    $project->config->packages->forget(fn (Dependency $dependency) => $dependency->value->repository->is($repository));
    /** @var Dependency $dependency */
    $dependency = $project->dependencies->first(fn (Dependency $dependency) => $dependency->value->repository->is($repository));
    PackageManager\remove($project, $dependency);

    line('Committing configs...');
    PackageManager\commit($project);

    success("Package $package_url has been removed successfully.");
};
