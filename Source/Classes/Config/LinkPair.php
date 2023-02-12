<?php

namespace Phpkg\Classes\Config;

use PhpRepos\Datatype\Pair;
use PhpRepos\FileManager\Filename;

class LinkPair extends Pair
{
    public function symlink(): Filename
    {
        return $this->key;
    }

    public function source(): Filename
    {
        return $this->value;
    }
}
