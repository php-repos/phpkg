<?php

namespace Phpkg\Classes\Meta;

use PhpRepos\Datatype\Map;

class Meta
{
    public function __construct(public Map $dependencies) {}

    public static function init(): static
    {
        return new static(new Map());
    }

    public static function from_array(array $meta): static
    {
        $dependencies = new Map();

        foreach ($meta['packages'] as $package_url => $package_meta) {
            $dependencies->push(new Dependency($package_url, $package_meta));
        }

        return new static($dependencies);
    }

    public function to_array(): array
    {
        return $this->dependencies->reduce(function (array $packages, Dependency $dependency) {
            $packages['packages'][$dependency->key] = [
                'owner' => $dependency->repository()->owner,
                'repo' => $dependency->repository()->repo,
                'version' => $dependency->repository()->version,
                'hash' => $dependency->repository()->hash,
            ];

            return $packages;
        }, ['packages' => []]);
    }
}