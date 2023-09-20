<?php

namespace Phpkg\Classes;

use Phpkg\Exception\PreRequirementsFailedException;
use Phpkg\Git\Repository;
use PhpRepos\FileManager\File;
use PhpRepos\FileManager\JsonFile;
use PhpRepos\FileManager\Path;
use function PhpRepos\FileManager\Directory\exists;
use function PhpRepos\FileManager\File;
use function PhpRepos\FileManager\JsonFile;

class Package
{
    public readonly Path $root;
    public readonly Config $config;

    public function __construct(
        public readonly Repository $repository,
    ) {}

    public static function from_config(string $package_url, $version): static
    {
        return new static(Repository::from_url($package_url)->version($version));
    }

    public static function from_meta(array $meta): static
    {
        return new static(Repository::from_meta($meta));
    }

    public function install(Path $root): static
    {
        if (! exists($root)) {
            throw new PreRequirementsFailedException('It seems you didn\'t run the install command. Please make sure you installed your required packages.');
        }

        $this->root = $root;
        $config = $root->append('phpkg.config.json');
        $this->config = File\exists($config) ? Config::from_array(JsonFile\to_array($config)) : Config::init();

        return $this;
    }
}
