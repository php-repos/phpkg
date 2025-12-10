<?php

namespace Phpkg\SoftwareSolutions\Versions;

use Phpkg\SoftwareSolutions\Data\Repository;
use Phpkg\SoftwareSolutions\Data\Version;
use Phpkg\SoftwareSolutions\Repositories;
use Phpkg\InfrastructureStructure\GitHosts;
use function Phpkg\InfrastructureStructure\Logs\debug;
use function Phpkg\InfrastructureStructure\Logs\log;

function are_equal(Version $a, Version $b): bool
{
    debug('Checking if versions are equal', [
        'version_a' => $a->identifier(),
        'version_b' => $b->identifier(),
    ]);
    return Repositories\are_equal($a->repository, $b->repository) && GitHosts\compare_versions($a->tag, $b->tag) === 0;
}

function from(string $url, string $version): Version
{
    debug('Creating version from URL and version', [
        'url' => $url,
        'version' => $version,
    ]);
    $repository = Repositories\from($url);
    return new Version($repository, $version);
}

function prepare(string $url, string $version, array $credentials): Version
{
    log('Preparing version data', [
        'url' => $url,
        'version' => $version,
    ]);
    $repository = Repositories\prepare($url, $credentials);
    return new Version($repository, $version);
}

function is_development(Version $version): bool
{
    log('Checking if version is development', [
        'version' => $version->identifier(),
    ]);
    return $version->tag === 'development';
}

function match_highest_version(string $url, string $version, array $credentials): Version
{
    log('Matching highest version', [
        'url' => $url,
        'version' => $version,
    ]);
    $repository = Repositories\prepare($url, $credentials);

    $version = GitHosts\match_highest_version($repository->domain, $repository->owner, $repository->repo, $version, $repository->token);

    if ($version === null) {
        throw new \InvalidArgumentException(sprintf('No matching version found for %s in repository %s.', $version, $repository->url));
    }

    return new Version($repository, $version);
}

function find_latest_version(Repository $repository): Version
{
    log('Finding latest version for repository', [
        'repository' => $repository->identifier(),
    ]);
    $version = GitHosts\find_latest_version($repository->domain, $repository->owner, $repository->repo, $repository->token);

    return new Version($repository, $version);
}
