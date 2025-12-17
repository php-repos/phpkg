<?php

namespace PhpRepos\Git\Hosts;

use PhpRepos\Git\Exception\ApiRequestException;
use PhpRepos\Git\Exception\InvalidTokenException;
use PhpRepos\Git\Exception\NotFoundException;
use PhpRepos\Git\Exception\RateLimitedException;
use PhpRepos\Git\Exception\UnsupportedHostException;
use PhpRepos\Git\GitHub;
use function PhpRepos\Datatype\Arr\first;
use function PhpRepos\SemanticVersioning\Tags\compare;
use function PhpRepos\SemanticVersioning\Tags\has_major_change;

/**
 * Get all tags from a Git repository on a supported host.
 *
 * @param string $domain The Git host domain (e.g., 'github.com').
 * @param string $owner The repository owner (username or organization).
 * @param string $repo The repository name.
 * @param string|null $token The authentication token for the host.
 * @return array Array of tag data from the repository.
 * @throws UnsupportedHostException If the domain is not supported.
 * @throws ApiRequestException If the API request fails.
 * @throws InvalidTokenException If the authentication token is not valid.
 * @throws RateLimitedException If the API rate limit is exceeded.
 * @throws NotFoundException If the repository is not found or inaccessible.
 */
function tags(string $domain, string $owner, string $repo, ?string $token): array
{
    if ($domain !== 'github.com') {
        throw new UnsupportedHostException('Unsupported domain: ' . $domain);
    }

    return GitHub\tags($owner, $repo, $token);
}

/**
 * Find the highest matching version for a given version pattern in a Git repository.
 *
 * @param string $domain The Git host domain (e.g., 'github.com').
 * @param string $owner The repository owner (username or organization).
 * @param string $repo The repository name.
 * @param string $version The version pattern to match against.
 * @param string|null $token The authentication token for the host.
 * @return string|null The highest matching version string, or null if no match found.
 * @throws UnsupportedHostException If the domain is not supported.
 * @throws ApiRequestException If the API request fails.
 * @throws InvalidTokenException If the authentication token is not valid.
 * @throws RateLimitedException If the API rate limit is exceeded.
 * @throws NotFoundException If the repository is not found or inaccessible.
 */
function match_highest_version(string $domain, string $owner, string $repo, string $version, ?string $token): ?string
{
    $tags = tags($domain, $owner, $repo, $token);

    usort($tags, function ($tag1, $tag2) {
        return compare($tag2['name'], $tag1['name']);
    });

    $exact_version = first($tags, fn($tag) => compare($version, $tag['name']) === 0);

    if ($exact_version) {
        return $exact_version['name'];
    }

    foreach ($tags as $tag) {
        if (!has_major_change($version, $tag['name']) && compare($version, $tag['name']) > 0) {
            return $tag['name'];
        }
    }

    return null;
}

/**
 * Find the commit hash for a specific version (tag) in a Git repository.
 *
 * @param string $domain The Git host domain (e.g., 'github.com').
 * @param string $owner The repository owner (username or organization).
 * @param string $repo The repository name.
 * @param string $version The version (tag) to find the hash for.
 * @param string|null $token The authentication token for the host.
 * @return string The commit hash (SHA) for the specified version.
 * @throws UnsupportedHostException If the domain is not supported.
 * @throws ApiRequestException If the API request fails.
 * @throws InvalidTokenException If the authentication token is not valid.
 * @throws RateLimitedException If the API rate limit is exceeded.
 * @throws NotFoundException If the repository or version is not found or inaccessible.
 */
function find_version_hash(string $domain, string $owner, string $repo, string $version, ?string $token): string
{
    if ($domain !== 'github.com') {
        throw new UnsupportedHostException('Unsupported domain: ' . $domain);
    }

    return GitHub\find_version_hash($owner, $repo, $version, $token);
}

/**
 * Find the commit hash of the latest commit in a Git repository.
 *
 * @param string $domain The Git host domain (e.g., 'github.com').
 * @param string $owner The repository owner (username or organization).
 * @param string $repo The repository name.
 * @param string|null $token The authentication token for the host.
 * @return string The commit hash (SHA) of the latest commit.
 * @throws UnsupportedHostException If the domain is not supported.
 * @throws ApiRequestException If the API request fails.
 * @throws InvalidTokenException If the authentication token is not valid.
 * @throws RateLimitedException If the API rate limit is exceeded.
 * @throws NotFoundException If the repository is not found or inaccessible.
 */
function find_latest_commit_hash(string $domain, string $owner, string $repo, ?string $token): string
{
    if ($domain !== 'github.com') {
        throw new UnsupportedHostException('Unsupported domain: ' . $domain);
    }

    return GitHub\find_latest_commit_hash($owner, $repo, $token);
}

/**
 * Check if a file exists at a specific path in a Git repository.
 *
 * @param string $domain The Git host domain (e.g., 'github.com').
 * @param string $owner The repository owner (username or organization).
 * @param string $repo The repository name.
 * @param string $hash The commit hash (SHA) to check the file at.
 * @param string|null $token The authentication token for the host.
 * @param string $file The file path to check.
 * @return bool True if the file exists, false otherwise.
 * @throws UnsupportedHostException If the domain is not supported.
 * @throws ApiRequestException If the API request fails.
 * @throws InvalidTokenException If the authentication token is not valid.
 * @throws RateLimitedException If the API rate limit is exceeded.
 */
function file_exists(string $domain, string $owner, string $repo, string $hash, ?string $token, string $file): bool
{
    if ($domain !== 'github.com') {
        throw new UnsupportedHostException('Unsupported domain: ' . $domain);
    }

    return GitHub\file_exists($owner, $repo, $hash, $token, $file);
}

/**
 * Get the content of a file from a Git repository.
 *
 * @param string $domain The Git host domain (e.g., 'github.com').
 * @param string $owner The repository owner (username or organization).
 * @param string $repo The repository name.
 * @param string $hash The commit hash (SHA) to get the file from.
 * @param string|null $token The authentication token for the host.
 * @param string $path The file path to get content from.
 * @return string The decoded content of the file.
 * @throws UnsupportedHostException If the domain is not supported.
 * @throws ApiRequestException If the API request fails.
 * @throws InvalidTokenException If the authentication token is not valid.
 * @throws RateLimitedException If the API rate limit is exceeded.
 * @throws NotFoundException If the file is not found or inaccessible.
 */
function file_content(string $domain, string $owner, string $repo, string $hash, ?string $token, string $path): string
{
    if ($domain !== 'github.com') {
        throw new UnsupportedHostException('Unsupported domain: ' . $domain);
    }

    return GitHub\file_content($owner, $repo, $hash, $token, $path);
}

/**
 * Download an archive from a Git repository.
 *
 * @param string $domain The Git host domain (e.g., 'github.com').
 * @param string $owner The repository owner (username or organization).
 * @param string $repo The repository name.
 * @param string $hash The commit hash (SHA) to download.
 * @param string|null $token The authentication token for the host.
 * @param string $path The destination path to save the archive.
 * @return bool True if the download was successful, false otherwise.
 * @throws UnsupportedHostException If the domain is not supported.
 * @throws InvalidTokenException If the authentication token is not valid.
 * @throws RateLimitedException If the API rate limit is exceeded.
 */
function download_archive(string $domain, string $owner, string $repo, string $hash, ?string $token, string $path): bool
{
    if ($domain !== 'github.com') {
        throw new UnsupportedHostException('Unsupported domain: ' . $domain);
    }

    return GitHub\download_archive($owner, $repo, $hash, $token, $path);
}

/**
 * Find the latest version (tag) of a Git repository.
 *
 * @param string $domain The Git host domain (e.g., 'github.com').
 * @param string $owner The repository owner (username or organization).
 * @param string $repo The repository name.
 * @param string|null $token The authentication token for the host.
 * @return string The latest version (tag) of the repository.
 * @throws UnsupportedHostException If the domain is not supported.
 * @throws ApiRequestException If the API request fails.
 * @throws InvalidTokenException If the authentication token is not valid.
 * @throws RateLimitedException If the API rate limit is exceeded.
 * @throws NotFoundException If the repository is not found or inaccessible.
 */
function find_latest_version(string $domain, string $owner, string $repo, ?string $token): string
{
    if ($domain !== 'github.com') {
        throw new UnsupportedHostException('Unsupported domain: ' . $domain);
    }
    return GitHub\find_latest_version($owner, $repo, $token);
}

/**
 * Check if a Git repository has any tags.
 *
 * @param string $domain The Git host domain (e.g., 'github.com').
 * @param string $owner The repository owner (username or organization).
 * @param string $repo The repository name.
 * @param string|null $token The authentication token for the host.
 * @return bool True if the repository has at least one tag, false otherwise.
 * @throws UnsupportedHostException If the domain is not supported.
 * @throws ApiRequestException If the API request fails.
 * @throws InvalidTokenException If the authentication token is not valid.
 * @throws RateLimitedException If the API rate limit is exceeded.
 * @throws NotFoundException If the repository is not found or inaccessible.
 */
function has_any_tag(string $domain, string $owner, string $repo, ?string $token): bool
{
    if ($domain !== 'github.com') {
        throw new UnsupportedHostException('Unsupported domain: ' . $domain);
    }

    return GitHub\has_any_tag($owner, $repo, $token);
}
