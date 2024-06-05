<?php

namespace Phpkg\Classes;

use Phpkg\Datatypes\Node;

/**
 * @property string $key
 * @property Package $value
 */
class Dependency extends Node
{
    public static function from_package(Package $package): static
    {
        $repository = $package->value;

        return new static(
            "owner:$repository->owner,repo:$repository->repo,version:$repository->version,hash:$repository->hash",
            $package
        );
    }
}
