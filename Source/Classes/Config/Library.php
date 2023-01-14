<?php

namespace Phpkg\Classes\Config;

use Phpkg\Git\Repository;
use PhpRepos\Datatype\Pair;

class Library extends Pair
{
    public function repository(): Repository
    {
        return $this->value;
    }

    public function meta(): array
    {
        return [
            'owner' => $this->value->owner,
            'repo' => $this->value->repo,
            'version' => $this->value->version,
            'hash' => $this->value->hash,
        ];
    }
}
