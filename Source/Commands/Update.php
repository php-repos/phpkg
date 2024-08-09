<?php

use Phpkg\Application\PackageManager;
use Phpkg\Classes\Package;
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
use function PhpRepos\FileManager\Directory\exists;

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
    #[Argument]
    #[LongOption('version')]
    #[Description("The version number of the package you want to update to. If not provided, the command will update to the latest available version.")]
    ?string $version = null,
    #[LongOption('force')]
    #[Description('Use this option to forcefully update the specified package, ignoring version compatibility checks.')]
    ?bool $force = false,
    #[LongOption('project')]
    #[Description('When working in a different directory, provide the relative project path for correct package placement.')]
    ?string $project = ''
) {
    $environment = Phpkg\System\environment();

    line('Updating package ' . $package_url . ' to ' . ($version ? 'version ' . $version : 'latest version') . '...');

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

    line('Finding package in configs...');
    if (! $project->config->packages->has(fn (Package $installed_package) => $installed_package->value->owner === $repository->owner && $installed_package->value->repo === $repository->repo)) {
        throw new PreRequirementsFailedException("Package $package_url does not found in your project!");
    }

    line('Setting package version...');

    if ($version === PackageManager\DEVELOPMENT_VERSION) {
        $repository->version = $version;
    } else {
        $repository->version = $version
            ? PackageManager\match_highest_version($repository, $version)
            : PackageManager\get_latest_version($repository);
    }

    line('Updating package...');
    $package = new Package($package_url, $repository);

    line('Updating configs...');
    $project->config->packages->push($package);

    PackageManager\update($project, $package);

    line('Committing new configs...');
    PackageManager\commit($project);

    success("Package $package_url has been updated.");
};
