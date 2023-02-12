<?php

namespace Phpkg\Classes\Build;

use Phpkg\Classes\Config\NamespaceFilePair;
use Phpkg\Classes\Config\NamespacePathPair;
use Phpkg\Classes\Package\Package;
use Phpkg\Classes\Project\Project;
use PhpRepos\Datatype\Map;
use PhpRepos\FileManager\Path;

class Build
{
    public Map $import_map;

    public Map $namespace_map;

    public function __construct(
        public Project $project,
        public string $environment,
    ) {
        $this->import_map = new Map();
    }

    public function root(): Path
    {
        return $this->project->root->append('builds')->append($this->environment);
    }

    public function packages_directory(): Path
    {
        return $this->root()->append($this->project->config->packages_directory);
    }

    public function package_root(Package $package): Path
    {
        return $this->packages_directory()->append("{$package->repository->owner}/{$package->repository->repo}");
    }

    public function load_namespace_map(): static
    {
        $this->namespace_map = new Map();

        $this->project->packages->each(function (Package $package) {
            $package->config->map->each(function (NamespaceFilePair $namespace_file) use ($package) {
                $this->namespace_map->push(new NamespacePathPair($namespace_file->namespace(), $this->package_root($package)->append($namespace_file->filename())));
            });
        });

        $this->project->config->map->each(function (NamespaceFilePair $namespace_file) {
            $this->namespace_map->push(new NamespacePathPair($namespace_file->namespace(), $this->root()->append($namespace_file->filename())));
        });

        return $this;
    }
}
