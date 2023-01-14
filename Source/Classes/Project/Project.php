<?php

namespace Phpkg\Classes\Project;

use Phpkg\Classes\Config\Config;
use Phpkg\Classes\Meta\Meta;
use Phpkg\Git\Repository;
use PhpRepos\Datatype\Collection;
use PhpRepos\FileManager\Filesystem\Directory;
use PhpRepos\FileManager\Filesystem\File;
use PhpRepos\FileManager\Filesystem\Filename;

class Project
{
    public File $config_file;
    public File $meta_file;
    public Directory $packages_directory;
    public Config $config;
    public Meta $meta;

    public Collection $packages;

    public function __construct(
        public Directory $root,
    ) {
        $this->config_file = (new Filename('phpkg.config.json'))->file($this->root);
        $this->meta_file = (new Filename('phpkg.config-lock.json'))->file($this->root);
        $this->packages = new Collection();
    }

    public function config(Config $config): static
    {
        $this->config = $config;
        $this->packages_directory = $this->config->packages_directory->directory($this->root);

        return $this;
    }

    public function package_directory(Repository $repository): Directory
    {
        return $this->packages_directory->subdirectory("$repository->owner/$repository->repo");
    }
}
