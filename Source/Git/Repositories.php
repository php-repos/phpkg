<?php

namespace Phpkg\Git\Repositories;

use Exception;
use JsonException;
use Phpkg\Git\Exception\GithubApiRequestException;
use Phpkg\Git\Exception\InvalidTokenException;
use Phpkg\Git\Exception\NotFoundException;
use Phpkg\Git\Exception\NotSupportedVersionControlException;
use Phpkg\Git\Exception\RateLimitedException;
use Phpkg\Git\Exception\UnauthenticatedRateLimitedException;
use Phpkg\Git\Repository;
use function Phpkg\Environments\github_token;
use function Phpkg\Environments\has_github_token;
use function Phpkg\System\environment;
use function PhpRepos\ControlFlow\Conditional\when;

/**
 * @throws JsonException
 */
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

/**
 * @throws JsonException
 * @throws Exception
 */
function download_archive(Repository $repository, string $root): bool
{
    return \Phpkg\Git\GitHub\download_archive(
        $root,
        $repository->owner,
        $repository->repo,
        $repository->hash,
        token($repository),
    );
}

/**
 * @throws GithubApiRequestException If the GitHub api fails.
 * @throws InvalidTokenException If the GitHub token is not valid.
 * @throws RateLimitedException If user gets rate limited.
 * @throws UnauthenticatedRateLimitedException If unauthenticated request gets rate limited.
 * @throws NotFoundException Either the URL is not valid or user does not have access.
 * @throws JsonException
 */
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

/**
 * @param Repository $repository
 * @param string $file
 * @return bool
 * @throws GithubApiRequestException If the GitHub api fails.
 * @throws InvalidTokenException If the GitHub token is not valid.
 * @throws RateLimitedException If user gets rate limited.
 * @throws UnauthenticatedRateLimitedException If unauthenticated request gets rate limited.
 * @throws JsonException
 */
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

/**
 * @param Repository $repository
 * @return string
 * @throws JsonException
 * @throws GithubApiRequestException If the GitHub api fails.
 * @throws InvalidTokenException If the GitHub token is not valid.
 * @throws RateLimitedException If user gets rate limited.
 * @throws UnauthenticatedRateLimitedException If unauthenticated request gets rate limited.
 * @throws NotFoundException Either the URL is not valid or user does not have access.
 */
function find_latest_commit_hash(Repository $repository): string
{
    return \Phpkg\Git\GitHub\find_latest_commit_hash(
        $repository->owner,
        $repository->repo,
        token($repository),
    );
}

/**
 * @param Repository $repository
 * @return string
 * @throws JsonException
 * @throws GithubApiRequestException If the GitHub api fails.
 * @throws InvalidTokenException If the GitHub token is not valid.
 * @throws RateLimitedException If user gets rate limited.
 * @throws UnauthenticatedRateLimitedException If unauthenticated request gets rate limited.
 * @throws NotFoundException Either the URL is not valid or user does not have access.
 */
function find_latest_version(Repository $repository): string
{
    return \Phpkg\Git\GitHub\find_latest_version(
        $repository->owner,
        $repository->repo,
        token($repository),
    );
}

/**
 * @param Repository $repository
 * @return string
 * @throws JsonException
 * @throws GithubApiRequestException If the GitHub api fails.
 * @throws InvalidTokenException If the GitHub token is not valid.
 * @throws RateLimitedException If user gets rate limited.
 * @throws UnauthenticatedRateLimitedException If unauthenticated request gets rate limited.
 * @throws NotFoundException Either the URL is not valid or user does not have access.
 */
function find_version_hash(Repository $repository): string
{
    return \Phpkg\Git\GitHub\find_version_hash(
        $repository->owner,
        $repository->repo,
        $repository->version,
        token($repository),
    );
}

/**
 * @param Repository $repository
 * @return bool
 * @throws JsonException
 * @throws GithubApiRequestException If the GitHub api fails.
 * @throws InvalidTokenException If the GitHub token is not valid.
 * @throws RateLimitedException If user gets rate limited.
 * @throws UnauthenticatedRateLimitedException If unauthenticated request gets rate limited.
 * @throws NotFoundException Either the URL is not valid or user does not have access.
 */
function has_any_tag(Repository $repository): bool
{
    return \Phpkg\Git\GitHub\has_any_tag(
        $repository->owner,
        $repository->repo,
        token($repository),
    );
}

/**
 * @param Repository $repository
 * @return array
 * @throws JsonException
 * @throws GithubApiRequestException If the GitHub api fails.
 * @throws InvalidTokenException If the GitHub token is not valid.
 * @throws RateLimitedException If user gets rate limited.
 * @throws UnauthenticatedRateLimitedException If unauthenticated request gets rate limited.
 * @throws NotFoundException Either the URL is not valid or user does not have access.
 */
function tags(Repository $repository): array
{
    return \Phpkg\Git\GitHub\tags(
        $repository->owner,
        $repository->repo,
        token($repository),
    );
}
