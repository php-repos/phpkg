<?php

namespace Phpkg\Classes\Package;

use Phpkg\Classes\Config\Config;
use Phpkg\Git\Repository;
use PhpRepos\FileManager\Path;

class Package
{
    public Path $config_file;
    public Config $config;

    public function __construct(
        public Path $root,
        public Repository $repository,
    ) {
        $this->config_file = $this->root->append('phpkg.config.json');
    }
}
