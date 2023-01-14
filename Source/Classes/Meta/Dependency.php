<?php

namespace Phpkg\Classes\Meta;

use Phpkg\Git\Repository;
use PhpRepos\Datatype\Pair;

class Dependency extends Pair
{
    public function repository(): Repository
    {
        return Repository::from_meta($this->value);
    }
}
