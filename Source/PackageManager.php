<?php

namespace Phpkg\PackageManager;

use Phpkg\Git\Repository;
use function Phpkg\Providers\GitHub\clone_to;
use function Phpkg\Providers\GitHub\find_latest_commit_hash;
use function Phpkg\Providers\GitHub\find_latest_version;
use function Phpkg\Providers\GitHub\find_version_hash;
use function Phpkg\Providers\GitHub\has_release;

const DEVELOPMENT_VERSION = 'development';

function get_latest_version(Repository $repository): string
{
    return has_release($repository->owner, $repository->repo)
        ? find_latest_version($repository->owner, $repository->repo)
        : DEVELOPMENT_VERSION;
}

function detect_hash(Repository $repository): string
{
    return $repository->version !== DEVELOPMENT_VERSION
        ? find_version_hash($repository->owner, $repository->repo, $repository->version)
        : find_latest_commit_hash($repository->owner, $repository->repo);
}

function download(Repository $repository, string $destination): bool
{
    if ($repository->version === DEVELOPMENT_VERSION) {
        return clone_to($destination, $repository->owner, $repository->repo);
    }

    return \Phpkg\Providers\GitHub\download($destination, $repository->owner, $repository->repo, $repository->version);
}
