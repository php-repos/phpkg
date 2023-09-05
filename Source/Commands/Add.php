<?php

use Phpkg\Application\Credentials;
use Phpkg\Application\PackageManager;
use Phpkg\Classes\Config\PackageAlias;
use Phpkg\Classes\Config\Library;
use Phpkg\Classes\Environment\Environment;
use Phpkg\Classes\Meta\Dependency;
use Phpkg\Classes\Project\Project;
use Phpkg\Exception\PreRequirementsFailedException;
use Phpkg\Git\Repository;
use PhpRepos\Console\Attributes\Description;
use PhpRepos\FileManager\Directory;
use PhpRepos\FileManager\File;
use PhpRepos\Console\Attributes\Argument;
use PhpRepos\Console\Attributes\LongOption;
use function PhpRepos\Cli\IO\Write\line;
use function PhpRepos\Cli\IO\Write\success;
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
    #[LongOption('project')]
    #[Description('When working in a different directory, provide the relative project path for correct package placement.')]
    string $project = ''
) {
    $environment = Environment::for_project();

    line('Adding package ' . $package_url . ($version ? ' version ' . $version : ' latest version') . '...');

    $project = new Project($environment->pwd->append($project));

    if (! File\exists($project->config_file)) {
        throw new PreRequirementsFailedException('Project is not initialized. Please try to initialize using the init command.');
    }

    line('Setting env credential...');
    Credentials\set_credentials($environment);

    line('Loading configs...');
    $project = PackageManager\load_config($project);

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
    PackageManager\add($project, $dependency);

    line('Updating configs...');
    $project->config->repositories->push($library);

    line('Committing configs...');

    PackageManager\commit($project);

    success("Package $package_url has been added successfully.");
};
