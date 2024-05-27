<?php

namespace Phpkg\Repository;

use Phpkg\Git\Repository;

function are_same_repositories(Repository $repository1, Repository $repository2): bool
{
    return $repository1->owner === $repository2->owner && $repository1->repo === $repository2->repo;
}
