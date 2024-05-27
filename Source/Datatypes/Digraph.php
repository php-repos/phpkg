<?php

namespace Phpkg\Datatypes;

use PhpRepos\Datatype\Collection;
use PhpRepos\Datatype\Map;

class Digraph
{
    public function __construct(public readonly Map $vertices, public readonly Collection $edges) {}

    public static function empty(): static
    {
        return new static(new Map(), new Collection());
    }
}
