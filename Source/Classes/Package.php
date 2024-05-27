<?php

namespace Phpkg\Classes;

use Phpkg\Git\Repository;
use PhpRepos\Datatype\Pair;

/**
 * @property string $key
 * @property Repository $value
 */
class Package extends Pair
{
    public static function from_config(string $package_url, $version): static
    {
        $repository = Repository::from_url($package_url);
        $repository->version = $version;

        return new static($package_url, $repository);
    }

    public static function from_meta(string $package_url, array $meta): static
    {
        $repository = new Repository(
            'github.com',
            $meta['owner'],
            $meta['repo'],
        );
        $repository->version = $meta['version'];
        $repository->hash = $meta['hash'];

        return new static($package_url, $repository);
    }
}
