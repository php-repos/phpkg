<?php

namespace Phpkg\Commands\Migrate;

use Phpkg\Classes\Config\Config;
use Phpkg\Classes\Config\NamespaceFilePair;
use Phpkg\Classes\Meta\Meta;
use Phpkg\Classes\Environment\Environment;
use Phpkg\Classes\Project\Project;
use Phpkg\Exception\PreRequirementsFailedException;
use PhpRepos\Cli\IO\Write;
use PhpRepos\Datatype\Map;
use PhpRepos\FileManager\Filename;
use PhpRepos\FileManager\File;
use PhpRepos\FileManager\JsonFile;
use function PhpRepos\Cli\IO\Read\parameter;

function run(Environment $environment): void
{
    $project = new Project($environment->pwd->append(parameter('project', '')));

    $composer_file = $project->root->append('composer.json');
    $composer_lock_file = $project->root->append('composer.lock');

    if (! File\exists($composer_file)) {
        throw new PreRequirementsFailedException('There is no composer.json file.');
    }

    if (! File\exists($composer_lock_file)) {
        throw new PreRequirementsFailedException('There is no composer.lock file.');
    }

    $composer_setting = JsonFile\to_array($composer_file);
    $composer_lock_setting = JsonFile\to_array($composer_lock_file);

    $config = Config::init();
    $config->packages_directory = new Filename('vendor');
    $meta = Meta::init();

    $project->config($config);
    $project->meta = $meta;

    migrate($project, $composer_setting, $composer_lock_setting);

    JsonFile\write($project->config_file, $project->config->to_array());
    JsonFile\write($project->meta_file, $project->meta->to_array());

    Write\success('Migration has been finished successfully.');
}

function migrate(Project $project, array $composer_setting, array $composer_lock_setting): void
{
    $project->config->map = psr4_to_map(
        array_merge(
        $composer_setting['autoload']['psr-4'] ?? [],
            $composer_setting['autoload-dev']['psr-4'] ?? []
        )
    );

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
