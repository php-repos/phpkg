<?php

namespace Phpkg\Classes;

class Meta
{
    public function __construct(public Dependencies $dependencies) {}

    public static function init(): static
    {
        return new static(new Dependencies());
    }

    public static function from_array(array $meta): static
    {
        $dependencies = new Dependencies();

        foreach ($meta['packages'] as $package_url => $package_meta) {
            $dependencies->push(new Dependency($package_url, Package::from_meta($package_meta)));
        }

        return new static($dependencies);
    }
}
