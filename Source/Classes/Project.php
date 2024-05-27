<?php

namespace Phpkg\Classes;

use JsonException;
use Phpkg\Exception\PreRequirementsFailedException;
use PhpRepos\FileManager\File;
use PhpRepos\FileManager\JsonFile;
use PhpRepos\FileManager\Path;

class Project
{
    public readonly Path $config_file;
    public readonly Path $config_lock_file;
    public readonly Path $packages_directory;
    public Config $config;
    public Meta $meta;
    public bool $check_semantic_versioning = true;
    public BuildMode $build_mode;

    public function __construct(
        public readonly Path $root,
    ) {
        $this->config_file = $this->root->append('phpkg.config.json');
        $this->config_lock_file = $this->root->append('phpkg.config-lock.json');
    }

    /**
     * @throws PreRequirementsFailedException
     * @throws JsonException
     */
    public static function initialized(Path $root): static
    {
        $project = new static($root);

        if (! File\exists($project->config_file)) {
            throw new PreRequirementsFailedException('Project is not initialized. Please try to initialize using the init command.');
        }

        $project->config(Config::from_array(JsonFile\to_array($project->config_file)));

        $meta = File\exists($project->config_lock_file)
            ? Meta::from_array(JsonFile\to_array($project->config_lock_file))
            : Meta::init();

        $project->meta = $meta;

        return $project;
    }

    public function config(Config $config): self
    {
        $this->config = $config;
        $this->packages_directory = $this->root->append($config->packages_directory);

        return $this;
    }
}
