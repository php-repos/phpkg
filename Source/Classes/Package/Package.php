<?php

namespace Phpkg\Classes\Package;

use Phpkg\Classes\Config\Config;
use Phpkg\Git\Repository;
use PhpRepos\FileManager\Filesystem\Directory;
use PhpRepos\FileManager\Filesystem\File;
use PhpRepos\FileManager\Filesystem\Filename;

class Package
{
    public File $config_file;
    public Config $config;

    public function __construct(
        public Directory $root,
        public Repository $repository,
    ) {
        $this->config_file = (new Filename('phpkg.config.json'))->file($this->root);
    }

    public function is_downloaded(): bool
    {
        return $this->root->exists();
    }

    public function download(): bool
    {
        return $this->repository->download($this->root);
    }
}
