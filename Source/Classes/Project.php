<?php

namespace Phpkg\Classes;

use Phpkg\Exception\PreRequirementsFailedException;
use PhpRepos\Datatype\Map;
use PhpRepos\FileManager\File;
use PhpRepos\FileManager\JsonFile;
use PhpRepos\FileManager\Path;
use function Phpkg\Application\PackageManager\build_package_path;
use function Phpkg\Application\PackageManager\build_root;
use function Phpkg\Application\PackageManager\package_path;

class Project
{
    public const CONFIG_FILENAME = 'phpkg.config.json';
    public const META_FILENAME = 'phpkg.config-lock.json';

    public readonly Path $packages_directory;
    public Config $config;
    public Meta $meta;
    public Dependencies $dependencies;
    public bool $check_semantic_versioning = true;
    public BuildMode $build_mode;
    public Map $import_map;
    public Map $namespace_map;

    public function __construct(
        public readonly Environment $environment,
        public readonly Path $root,
    ) {}

    public static function initialized(Environment $environment, Path $root): static
    {
        if (! File\exists($root->append(self::CONFIG_FILENAME))) {
            throw new PreRequirementsFailedException('Project is not initialized. Please try to initialize using the init command.');
        }

        $project = new static($environment, $root);
        $project->config(Config::from_array(JsonFile\to_array($project->root->append(self::CONFIG_FILENAME))));

        $meta = File\exists($project->root->append(self::META_FILENAME))
            ? Meta::from_array(JsonFile\to_array($project->root->append(self::META_FILENAME)))
            : Meta::init();

        $project->meta = $meta;

        return $project;
    }

    public static function installed(Environment $environment, Path $root, BuildMode $build_mode = BuildMode::Development): static
    {
        $project = self::initialized($environment, $root);
        $project->build_mode = $build_mode;
        $project->dependencies = new Dependencies();
        $project->meta->dependencies->each(function (Dependency $dependency) use ($project) {
            $package_root = package_path($project, $dependency->value->repository);
            $package = $dependency->value->install($package_root);
            $project->dependencies->push($dependency->value($package));
        });

        $project->import_map = new Map();
        $project->namespace_map = new Map();
        $project->dependencies->each(function (Dependency $dependency) use ($project) {
            $dependency->value->config->map->each(function (NamespaceFilePair $namespace_file) use ($project, $dependency) {
                $project->namespace_map->push(new NamespacePathPair($namespace_file->key, build_package_path($project, $dependency->value->repository)->append($namespace_file->value)));
            });
        });

        $project->config->map->each(function (NamespaceFilePair $namespace_file) use ($project) {
            $project->namespace_map->push(new NamespacePathPair($namespace_file->key, build_root($project)->append($namespace_file->value)));
        });

        return $project;
    }

    public function config(Config $config): self
    {
        $this->config = $config;
        $this->packages_directory = $this->root->append($config->packages_directory);

        return $this;
    }
}
