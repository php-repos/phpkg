<?php

namespace Phpkg\Classes;

use PhpRepos\Datatype\Collection;
use PhpRepos\Datatype\Map;
use PhpRepos\FileManager\Filename;

class Config
{
    public function __construct(
        public Map $map,
        public Collection $excludes,
        public Collection $entry_points,
        public Map $executables,
        public Filename $packages_directory,
        public Dependencies $packages,
        public Map $aliases,
    ) {}

    public static function init(): static
    {
        return new static(
            new Map(),
            new Collection(),
            new Collection(),
            new Map(),
            new Filename('Packages'),
            new Dependencies(),
            new Map(),
        );
    }

    public static function from_array(array $config): static
    {
        $config['map'] = $config['map'] ?? [];
        $config['excludes'] = $config['excludes'] ?? [];
        $config['executables'] = $config['executables'] ?? [];
        $config['entry-points'] = $config['entry-points'] ?? [];
        $config['packages'] = $config['packages'] ?? [];
        $config['aliases'] = $config['aliases'] ?? [];

        $map = new Map();
        $excludes = new Collection();
        $executables = new Map();
        $entry_points = new Collection();
        $packages_directory = new Filename($config['packages-directory'] ?? 'Packages');
        $packages = new Dependencies();
        $aliases = new Map();

        foreach ($config['map'] as $namespace => $path) {
            $map->push(new NamespaceFilePair($namespace, new Filename($path)));
        }

        foreach ($config['excludes'] as $exclude) {
            $excludes->push(new Filename($exclude));
        }
        
        foreach ($config['executables'] as $symlink => $file) {
            $executables->push(new LinkPair(new Filename($symlink), new Filename($file)));
        }

        foreach ($config['entry-points'] as $entrypoint) {
            $entry_points->push(new Filename($entrypoint));
        }
        
        foreach ($config['packages'] as $package_url => $version) {
            $packages->push(new Dependency($package_url, Package::from_config($package_url, $version)));
        }

        foreach ($config['aliases'] as $alias => $package_url) {
            $aliases->push(new PackageAlias($alias, $package_url));
        }

        return new static($map, $excludes, $entry_points, $executables, $packages_directory, $packages, $aliases);
    }
}