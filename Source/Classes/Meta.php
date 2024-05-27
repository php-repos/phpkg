<?php

namespace Phpkg\Classes;

use PhpRepos\Datatype\Collection;

class Meta
{
    public function __construct(public Collection $packages) {}

    public static function init(): static
    {
        return new static(new Collection());
    }

    public static function from_array(array $meta): static
    {
        $packages = new Collection();

        $meta['packages'] = $meta['packages'] ?? [];

        foreach ($meta['packages'] as $package_url => $package_meta) {
            $packages->push(Package::from_meta($package_url, $package_meta));
        }

        return new static($packages);
    }
}
