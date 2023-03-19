<?php

namespace Phpkg\Application\Migrator;

use Phpkg\Classes\Config\NamespaceFilePair;
use Phpkg\Classes\Project\Project;
use PhpRepos\Datatype\Map;
use PhpRepos\FileManager\Filename;
use function PhpRepos\ControlFlow\Conditional\unless;

function migrate(Project $project, array $composer_setting, array $composer_lock_setting): void
{
    $project->config->map = psr4_to_map(array_merge(
        $composer_setting['autoload']['psr-4'] ?? [],
        $composer_setting['autoload-dev']['psr-4'] ?? []
    ));

    $installed_packages = array_merge(
        $composer_lock_setting['packages'] ?? [],
        $composer_lock_setting['packages-dev'] ?? [],
    );

    foreach ($installed_packages as $package_meta) {
        psr4_to_map($package_meta['autoload']['psr-4'] ?? [])
            ->each(function (NamespaceFilePair $namespace_file_pair) use ($project, $package_meta) {
                $package_namespace_filename = $namespace_file_pair->value(new Filename('vendor/' . $package_meta['name'] . '/' . $namespace_file_pair->filename()));
                unless(
                    $project->config->map->has(fn (NamespaceFilePair $map) => $map->namespace() === $namespace_file_pair->namespace()),
                    fn () => $project->config->map->push($package_namespace_filename)
                );
            });
    }
}

function psr4_to_map(array $psr4): Map
{
    $map = new Map();

    foreach ($psr4 as $namespace => $path) {
        if (! is_array($namespace) && ! is_array($path)) {
            $namespace = str_ends_with($namespace, '\\') ? substr_replace($namespace, '', -1) : $namespace;
            $path = str_ends_with($path, '/') ? substr_replace($path, '', -1) : $path;

            $map->push(new NamespaceFilePair($namespace, new Filename($path)));
        }
    }

    return $map;
}
