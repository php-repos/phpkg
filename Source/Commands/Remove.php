<?php

namespace Saeghe\Saeghe\Commands\Remove;

use Saeghe\Saeghe\Config;
use Saeghe\Saeghe\Meta;
use Saeghe\Saeghe\Package;
use Saeghe\Saeghe\Project;
use function Saeghe\Cli\IO\Read\argument;
use function Saeghe\Cli\IO\Write\error;
use function Saeghe\Cli\IO\Write\success;
use function Saeghe\FileManager\Directory\delete_recursive;

function run(Project $project)
{
    $givenPackageUrl = argument(2);

    $config = Config::fromArray(json_to_array($project->configFilePath->toString()));

    $package = array_reduce(
        $config->packages,
        function ($carry, Package $package) {
            return $package->is($carry) ? $package : $carry;
        },
        Package::fromUrl($givenPackageUrl)
    );

    if (! isset($package->version)) {
        error("Package $givenPackageUrl does not found in your project!");

        return;
    }

    $packageUrl = $givenPackageUrl;

    foreach ($config->packages as $installedPackageUrl => $configPackage) {
        if ($configPackage->is($package)) {
            $packageUrl = $installedPackageUrl;
            break;
        }
    }

    remove($project, $config, $package, $packageUrl);

    unset($config->packages[$packageUrl]);
    json_put($project->configFilePath->toString(), $config->toArray());

    success("Package $givenPackageUrl has been removed successfully.");
}

function remove(Project $project, Config $config, Package $package, $packageUrl)
{
    $packageConfig = Config::fromArray(json_to_array($package->configPath($project, $config)->toString()));

    foreach ($packageConfig->packages as $subPackageUrl => $subPackage) {
        $subPackageHasBeenUsed = false;
        foreach ($config->packages as $usedPackages) {
            $subPackageHasBeenUsed = $subPackageHasBeenUsed || $usedPackages->is($subPackage);
        }

        if (! $subPackageHasBeenUsed) {
            remove($project, $config, $subPackage, $subPackageUrl);
        }
    }

    delete_recursive($package->root($project, $config)->toString());

    $meta = Meta::fromArray(json_to_array($project->configLockFilePath->toString()));

    unset($meta->packages[$packageUrl]);
    json_put($project->configLockFilePath->toString(), $meta->toArray());
}
