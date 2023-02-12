<?php

namespace Phpkg\Classes\Config;

use PhpRepos\Datatype\Pair;
use PhpRepos\FileManager\Filename;

class NamespaceFilePair extends Pair
{
    public function namespace(): string
    {
        return $this->key;
    }

    public function filename(): Filename
    {
        return $this->value;
    }
}
