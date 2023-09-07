<?php

use Phpkg\Application\Credentials;
use Phpkg\Application\PackageManager;
use Phpkg\Classes\Config\PackageAlias;
use Phpkg\Classes\Config\Library;
use Phpkg\Classes\Environment\Environment;
use Phpkg\Classes\Meta\Dependency;
use Phpkg\Classes\Package\Package;
use Phpkg\Classes\Project\Project;
use Phpkg\Exception\PreRequirementsFailedException;
use Phpkg\Git\Repository;
use PhpRepos\Console\Attributes\Argument;
use PhpRepos\Console\Attributes\Description;
use PhpRepos\Console\Attributes\LongOption;
use PhpRepos\FileManager\Directory;
use PhpRepos\FileManager\File;
use function PhpRepos\Cli\IO\Write\line;
use function PhpRepos\Cli\IO\Write\success;
use function PhpRepos\ControlFlow\Conditional\when_exists;

/**
 * Allows you to update the version of a specified package in your PHP project.
 * If you need to obtain the latest version of an added package, this command can be used. It requires a mandatory
 * `package_url` argument, which should be a valid Git URL (SSH or HTTPS) pointing to the desired package, or an alias
 * registered using the `alias` command. Additionally, you have the option to pass an `--version` option. If provided,
 * `phpkg` will download the exact specified version; otherwise, it will fetch the latest available version.
 */
return function (
    #[Argument]
    #[Description("The Git URL (SSH or HTTPS) of the package you want to update. Alternatively, if you have defined an alias for the package, you can use the alias instead.")]
    string $package_url,
    #[LongOption('version')]
    #[Description("The version number of the package you want to update to. If not provided, the command will update to the latest available version.")]
    ?string $version = null,
    #[LongOption('project')]
    #[Description('When working in a different directory, provide the relative project path for correct package placement.')]
    ?string $project = ''
) {
    $environment = Environment::for_project();

    line('Updating package ' . $package_url . ' to ' . ($version ? 'version ' . $version : 'latest version') . '...');

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

    line('Finding package in configs...');
    $library = $project->config->repositories->first(fn (Library $library) => $library->repository()->is($repository));
    $dependency = when_exists($library, fn (Library $library)
        => $project->meta->dependencies->first(fn (Dependency $dependency)
            => $dependency->repository()->is($library->repository())));

    if (! $library instanceof Library || ! $dependency instanceof Dependency) {
        throw new PreRequirementsFailedException("Package $package_url does not found in your project!");
    }

    line('Setting package version...');
    $library->repository()->version($version ?? PackageManager\get_latest_version($library->repository()));

    line('Loading package\'s config...');
    $packages_installed = $project->meta->dependencies->every(function (Dependency $dependency) use ($project) {
        $package = new Package($project->package_directory($dependency->repository()), $dependency->repository());
        return Directory\exists($package->root);
    });

    if (! $packages_installed) {
        throw new PreRequirementsFailedException('It seems you didn\'t run the install command. Please make sure you installed your required packages.');
    }

    $project = PackageManager\load_packages($project);

    line('Deleting package\'s files...');
    PackageManager\delete($project, $dependency);

    line('Detecting version hash...');
    $library->repository()->hash(PackageManager\detect_hash($library->repository()));

    line('Downloading the package with new version...');
    $dependency = new Dependency($package_url, $library->meta());
    PackageManager\add($project, $dependency);

    line('Updating configs...');
    $project->config->repositories->push($library);

    line('Committing new configs...');
    PackageManager\commit($project);

    success("Package $package_url has been updated.");
};
