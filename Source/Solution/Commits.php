<?php

namespace Phpkg\Solution\Commits;

use Phpkg\Infra\Exception\ArchiveDownloadException;
use Phpkg\Solution\Data\Commit;
use Phpkg\Solution\Data\Version;
use Phpkg\Solution\Exceptions\ComposerConfigFileNotFound;
use Phpkg\Solution\Exceptions\RemoteConfigNotFound;
use Phpkg\Solution\Versions;
use Phpkg\Solution\Paths;
use Phpkg\Infra\Exception\RemoteFileNotFoundException;
use Phpkg\Infra\Files;
use Phpkg\Infra\GitHosts;
use PhpRepos\Git\Exception\ApiRequestException;
use PhpRepos\Git\Exception\NotFoundException;
use function Phpkg\Infra\Logs\debug;
use function Phpkg\Infra\Logs\log;

function are_equal(Commit $a, Commit $b): bool
{
    debug('Checking if commits are equal', [
        'commit_a' => $a->hash,
        'commit_b' => $b->hash,
    ]);
    return Versions\are_equal($a->version, $b->version) && $a->hash === $b->hash;
}

function prepare(string $url, string $version, string $hash, array $credentials): Commit
{
    log('Preparing commit data', [
        'url' => $url,
        'version' => $version,
        'hash' => $hash,
    ]);

    $version = Versions\prepare($url, $version, $credentials);
    return new Commit($version, $hash);
}

/**
 * @throws NotFoundException
 * @throws ApiRequestException
 */
function find_version_commit(Version $version): Commit
{
    log('Finding commit for version', [
        'version' => $version->identifier(),
    ]);
    $hash = GitHosts\find_version_hash(
        $version->repository->domain,
        $version->repository->owner,
        $version->repository->repo,
        $version->tag,
        $version->repository->token
    );

    log('Found commit hash', [
        'version' => $version->identifier(),
        'hash' => $hash,
    ]);

    return new Commit($version, $hash);
}

/**
 * @throws NotFoundException
 * @throws ApiRequestException
 */
function find_latest_commit(Version $version): Commit
{
    log('Finding latest commit for version', [
        'version' => $version->identifier(),
    ]);
    $hash = GitHosts\find_latest_commit_hash($version->repository->domain, $version->repository->owner, $version->repository->repo, $version->repository->token);
    log('Found latest commit hash', [
        'version' => $version->identifier(),
        'hash' => $hash,
    ]);

    return new Commit($version, $hash);
}

/**
 * @throws ApiRequestException
 */
function remote_phpkg_exists(Commit $commit): bool
{
    log('Checking if remote phpkg config exists for commit', [
        'commit' => $commit->hash,
        'version' => $commit->version->tag,
        'repository' => $commit->version->repository->identifier(),
    ]);
    return GitHosts\file_exists($commit->version->repository->domain,
        $commit->version->repository->owner,
        $commit->version->repository->repo,
        $commit->hash,
        $commit->version->repository->token,
        'phpkg.config.json',
    );
}

/**
 * @throws ApiRequestException
 */
function remote_composer_exists(Commit $commit): bool
{
    log('Checking if remote composer config exists for commit', [
        'commit' => $commit->hash,
        'version' => $commit->version->tag,
        'repository' => $commit->version->repository->identifier(),
    ]);
    return GitHosts\file_exists($commit->version->repository->domain,
        $commit->version->repository->owner,
        $commit->version->repository->repo,
        $commit->hash,
        $commit->version->repository->token,
        'composer.json',
    );
}

/**
 * @param Commit $commit
 * @return array
 * @throws ApiRequestException
 * @throws RemoteConfigNotFound
 */
function get_remote_phpkg(Commit $commit): array
{
    log('Getting remote phpkg config for commit', [
        'commit' => $commit->hash,
        'version' => $commit->version->tag,
        'repository' => $commit->version->repository->identifier(),
    ]);
    try {
        return GitHosts\file_content_as_array(
            $commit->version->repository->domain,
            $commit->version->repository->owner,
            $commit->version->repository->repo,
            $commit->hash,
            $commit->version->repository->token,
            'phpkg.config.json',
        );
    } catch (RemoteFileNotFoundException) {
        $owner = $commit->version->repository->owner;
        $repo = $commit->version->repository->repo;
        $version = $commit->version->tag;
        $hash = $commit->hash;
        throw new RemoteConfigNotFound("Config file not found for $owner/$repo version $version commit hash $hash.");
    }
}

/**
 * @param Commit $commit
 * @return array
 * @throws ApiRequestException
 * @throws ComposerConfigFileNotFound
 */
function get_remote_composer(Commit $commit): array
{
    log('Getting remote composer config for commit', [
        'commit' => $commit->hash,
        'version' => $commit->version->tag,
        'repository' => $commit->version->repository->identifier(),
    ]);
    try {
        return GitHosts\file_content_as_array(
            $commit->version->repository->domain,
            $commit->version->repository->owner,
            $commit->version->repository->repo,
            $commit->hash,
            $commit->version->repository->token,
            'composer.json',
        );
    } catch (RemoteFileNotFoundException) {
        $owner = $commit->version->repository->owner;
        $repo = $commit->version->repository->repo;
        $version = $commit->version->tag;
        $hash = $commit->hash;
        throw new ComposerConfigFileNotFound("Composer Config file not found for $owner/$repo version $version commit hash $hash.");
    }
}

/**
 * @throws ArchiveDownloadException
 */
function download_zip(Commit $commit, string $destination): bool
{
    log('Downloading commit zip to destination', [
        'commit' => $commit->identifier(),
        'destination' => $destination,
    ]);

    Paths\ensure_directory_exists(Files\parent($destination));

    $download_status = GitHosts\download(
        $commit->version->repository->domain,
        $commit->version->repository->owner,
        $commit->version->repository->repo,
        $commit->hash,
        $commit->version->repository->token,
        $destination,
    );

    if (!$download_status) {
        log('Failed to download package zip', [
            'commit' => $commit->identifier(),
            'destination' => $destination,
        ]);
        return false;
    }

    return true;
}


