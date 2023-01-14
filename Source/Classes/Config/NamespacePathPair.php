<?php

namespace Phpkg\Classes\Config;

use PhpRepos\Datatype\Pair;
use PhpRepos\FileManager\Path;

class NamespacePathPair extends Pair
{
    public function namespace(): string
    {
        return $this->key;
    }

    public function path(): Path
    {
        return $this->value;
    }
}
