<?php

namespace Phpkg\Classes\Config;

use PhpRepos\Datatype\Pair;

class PackageAlias extends Pair
{
    public function alias(): string
    {
        return $this->key;
    }

    public function package_url(): string
    {
        return $this->value;
    }
}
