<?php

namespace Phpkg\Classes\Project;

use Phpkg\Classes\Config\Config;
use Phpkg\Classes\Meta\Meta;
use Phpkg\Git\Repository;
use PhpRepos\Datatype\Collection;
use PhpRepos\FileManager\Path;

class Project
{
    public Path $config_file;
    public Path $meta_file;
    public Path $packages_directory;
    public Config $config;
    public Meta $meta;

    public Collection $packages;

    public function __construct(
        public Path $root,
    ) {
        $this->config_file = $this->root->append('phpkg.config.json');
        $this->meta_file = $this->root->append('phpkg.config-lock.json');
        $this->packages = new Collection();
    }

    public function config(Config $config): static
    {
        $this->config = $config;
        $this->packages_directory = $this->root->append($this->config->packages_directory);

        return $this;
    }

    public function package_directory(Repository $repository): Path
    {
        return $this->packages_directory->append("$repository->owner/$repository->repo");
    }
}
