<?php

namespace Phpkg\Git\Repositories;

use Phpkg\Git\Exception\NotSupportedVersionControlException;
use Phpkg\Git\Repository;
use function Phpkg\Environments\github_token;
use function Phpkg\Environments\has_github_token;
use function Phpkg\System\environment;
use function PhpRepos\ControlFlow\Conditional\when;

function token(Repository $repository): ?string
{
    if (! $repository->is_github()) {
        throw new NotSupportedVersionControlException("I am so sorry, but the $repository->domain is not supported yet.");
    }

    return when(
        has_github_token(environment()),
        fn () => github_token(environment()),
        fn () => null,
    );
}

function download(Repository $repository, string $root): bool
{
    return \Phpkg\Git\GitHub\download(
        $root,
        $repository->owner,
        $repository->repo,
        $repository->hash,
        token($repository),
    );
}

function file_content(Repository $repository, string $file): string
{
    return \Phpkg\Git\GitHub\file_content(
        $repository->owner,
        $repository->repo,
        $repository->hash,
        $file,
        token($repository),
    );
}

function file_exists(Repository $repository, string $file): bool
{
    return \Phpkg\Git\GitHub\file_exists(
        $repository->owner,
        $repository->repo,
        $repository->hash,
        $file,
        token($repository),
    );
}

function find_latest_commit_hash(Repository $repository)
{
    return \Phpkg\Git\GitHub\find_latest_commit_hash(
        $repository->owner,
        $repository->repo,
        token($repository),
    );
}

function find_latest_version(Repository $repository): string
{
    return \Phpkg\Git\GitHub\find_latest_version(
        $repository->owner,
        $repository->repo,
        token($repository),
    );
}

function find_version_hash(Repository $repository): string
{
    return \Phpkg\Git\GitHub\find_version_hash(
        $repository->owner,
        $repository->repo,
        $repository->version,
        token($repository),
    );
}

function has_any_tag(Repository $repository): bool
{
    return \Phpkg\Git\GitHub\has_any_tag(
        $repository->owner,
        $repository->repo,
        token($repository),
    );
}

function tags(Repository $repository): array
{
    return \Phpkg\Git\GitHub\tags(
        $repository->owner,
        $repository->repo,
        token($repository),
    );
}
