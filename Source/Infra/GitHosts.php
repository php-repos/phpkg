<?php

namespace Phpkg\Infra\GitHosts;

use Exception;
use Phpkg\Infra\Exception\ArchiveDownloadException;
use Phpkg\Infra\Exception\RemoteFileNotFoundException;
use PhpRepos\Git\Exception\ApiRequestException;
use PhpRepos\Git\Exception\NotFoundException;
use PhpRepos\Git\Hosts;
use function Phpkg\Infra\Arrays\json_to_array;
use function PhpRepos\SemanticVersioning\Tags\compare;
use function PhpRepos\SemanticVersioning\Tags\major;

/**
 * Finds the highest version that matches the given version constraint.
 *
 * Searches for the highest available version that satisfies the version constraint
 * (e.g., "1.0.*", "^2.0", ">=1.5.0") from the specified Git repository.
 *
 * @param string $domain The Git host domain (e.g., 'github.com', 'gitlab.com')
 * @param string $owner The repository owner/organization
 * @param string $repo The repository name
 * @param string $version The version constraint to match
 * @param string|null $token Optional authentication token for private repositories
 * @return string|null The highest matching version string, or null if no match found
 *
 * @throws ApiRequestException
 * @throws NotFoundException
 * @example
 * ```php
 * $version = match_highest_version('github.com', 'php-repos', 'cli', '^1.0', 'ghp_token123');
 * // Returns: '1.5.2' (highest version matching ^1.0)
 * ```
 */
function match_highest_version(string $domain, string $owner, string $repo, string $version, ?string $token): ?string
{
    return Hosts\match_highest_version($domain, $owner, $repo, $version, $token);
}

/**
 * Finds the commit hash for a specific version tag.
 *
 * Retrieves the Git commit hash associated with a specific version tag
 * from the specified repository.
 *
 * @param string $domain The Git host domain (e.g., 'github.com', 'gitlab.com')
 * @param string $owner The repository owner/organization
 * @param string $repo The repository name
 * @param string $version The version tag to find the hash for
 * @param string|null $token Optional authentication token for private repositories
 * @return string The commit hash for the specified version
 *
 * @throws NotFoundException
 * @throws ApiRequestException
 * @example
 * ```php
 * $hash = find_version_hash('github.com', 'php-repos', 'cli', 'v1.0.0', 'ghp_token123');
 * // Returns: 'a1b2c3d4e5f6...'
 * ```
 */
function find_version_hash(string $domain, string $owner, string $repo, string $version, ?string $token): string
{
    return Hosts\find_version_hash($domain, $owner, $repo, $version, $token);
}

/**
 * Finds the latest available version tag in the repository.
 *
 * Retrieves the most recent version tag from the specified Git repository.
 *
 * @param string $domain The Git host domain (e.g., 'github.com', 'gitlab.com')
 * @param string $owner The repository owner/organization
 * @param string $repo The repository name
 * @param string|null $token Optional authentication token for private repositories
 * @return string The latest version tag string
 *
 * @throws ApiRequestException
 * @throws NotFoundException
 * @example
 * ```php
 * $latest = find_latest_version('github.com', 'php-repos', 'cli', 'ghp_token123');
 * // Returns: 'v2.1.0'
 * ```
 */
function find_latest_version(string $domain, string $owner, string $repo, ?string $token): string
{
    return Hosts\find_latest_version($domain, $owner, $repo, $token);
}

/**
 * Finds the latest commit hash from the default branch.
 *
 * Retrieves the most recent commit hash from the default branch
 * (usually 'main' or 'master') of the specified repository.
 *
 * @param string $domain The Git host domain (e.g., 'github.com', 'gitlab.com')
 * @param string $owner The repository owner/organization
 * @param string $repo The repository name
 * @param string|null $token Optional authentication token for private repositories
 * @return string The latest commit hash
 *
 * @throws ApiRequestException
 * @throws NotFoundException
 * @example
 * ```php
 * $hash = find_latest_commit_hash('github.com', 'php-repos', 'cli', 'ghp_token123');
 * // Returns: 'f9e8d7c6b5a4...'
 * ```
 */
function find_latest_commit_hash(string $domain, string $owner, string $repo, ?string $token): string
{
    return Hosts\find_latest_commit_hash($domain, $owner, $repo, $token);
}

/**
 * Checks if a specific file exists in a Git repository at a given commit.
 *
 * @param string $domain The Git host domain (e.g., 'github.com', 'gitlab.com')
 * @param string $owner The repository owner/organization
 * @param string $repo The repository name
 * @param string $hash The commit hash to check the file in
 * @param string|null $token Optional authentication token for private repositories
 * @param string $file The file path within the repository
 * @return bool True if the file exists, false otherwise
 *
 * @throws ApiRequestException
 * @example
 * ```php
 * $exists = file_exists('github.com', 'php-repos', 'cli', 'a1b2c3d4', 'ghp_token123', 'composer.json');
 * if ($exists) {
 *     echo "composer.json exists in this commit";
 * }
 * ```
 */
function file_exists(string $domain, string $owner, string $repo, string $hash, ?string $token, string $file): bool
{
    return Hosts\file_exists($domain, $owner, $repo, $hash, $token, $file);
}

/**
 * Retrieves the content of a file from a Git repository and converts it to an array.
 *
 * Downloads the file content and attempts to parse it as JSON, returning the result as an array.
 * This is commonly used for configuration files like composer.json, package.json, etc.
 *
 * @param string $domain The Git host domain (e.g., 'github.com', 'gitlab.com')
 * @param string $owner The repository owner/organization
 * @param string $repo The repository name
 * @param string $hash The commit hash to retrieve the file from
 * @param string|null $token Optional authentication token for private repositories
 * @param string $file The file path within the repository
 * @return array The parsed JSON content as an array
 *
 * @throws ApiRequestException
 * @throws RemoteFileNotFoundException When the file is not found in the repository*@throws ApiRequestException
 * @example
 * ```php
 * $config = file_content_as_array('github.com', 'php-repos', 'cli', 'a1b2c3d4', 'ghp_token123', 'composer.json');
 * $name = $config['name']; // 'php-repos/cli'
 * $version = $config['version']; // '1.0.0'
 * ```
 */
function file_content_as_array(string $domain, string $owner, string $repo, string $hash, ?string $token, string $file): array
{
    try {
        return json_to_array(Hosts\file_content($domain, $owner, $repo, $hash, $token, $file));
    } catch (NotFoundException $exception) {
        throw new RemoteFileNotFoundException($exception->getMessage());
    }
}

/**
 * Downloads an archive (ZIP/TAR) of a Git repository at a specific commit.
 *
 * Downloads the repository as a compressed archive file to the specified local path.
 * The archive contains the complete repository state at the given commit.
 *
 * @param string $domain The Git host domain (e.g., 'github.com', 'gitlab.com')
 * @param string $owner The repository owner/organization
 * @param string $repo The repository name
 * @param string $hash The commit hash to download
 * @param string|null $token Optional authentication token for private repositories
 * @param string $path The local path where the archive will be saved
 * @return bool
 *
 * @throws ArchiveDownloadException When the archive download fails
 */
function download(string $domain, string $owner, string $repo, string $hash, ?string $token, string $path): bool
{
    try {
        return Hosts\download_archive($domain, $owner, $repo, $hash, $token, $path);
    } catch (Exception $exception) {
        throw new ArchiveDownloadException($exception->getMessage());
    }
}

/**
 * Checks if the repository has any version tags.
 *
 * @param string $domain The Git host domain (e.g., 'github.com', 'gitlab.com')
 * @param string $owner The repository owner/organization
 * @param string $repo The repository name
 * @param string|null $token Optional authentication token for private repositories
 * @return string|null The first available tag, or null if no tags exist
 *
 * @throws ApiRequestException
 * @throws NotFoundException
 * @example
 * ```php
 * $tag = has_any_tag('github.com', 'php-repos', 'cli', 'ghp_token123');
 * if ($tag) {
 *     echo "Repository has tags, first one is: " . $tag;
 * } else {
 *     echo "Repository has no tags";
 * }
 * ```
 */
function has_any_tag(string $domain, string $owner, string $repo, ?string $token): ?string
{
    return Hosts\has_any_tag($domain, $owner, $repo, $token);
}

/**
 * Compares two semantic version strings.
 *
 * Compares two version strings and returns an integer indicating their relationship:
 * - Negative value: first version is lower than second
 * - Zero: versions are equal
 * - Positive value: first version is higher than second
 *
 * @param string $v1 The first version string to compare
 * @param string $v2 The second version string to compare
 * @return int -1 if v1 < v2, 0 if v1 == v2, 1 if v1 > v2
 *
 * @example
 * ```php
 * $result = compare_versions('1.2.3', '1.2.4');
 * if ($result < 0) {
 *     echo "1.2.3 is older than 1.2.4";
 * }
 * ```
 */
function compare_versions(string $v1, string $v2): int
{
    return compare($v1, $v2);
}

/**
 * Extracts the major version part from a semantic version string.
 *
 * @param string $version The semantic version string (e.g., '1.2.3', 'v2.0.0')
 * @return string The major version part
 *
 * @example
 * ```php
 * $major = major_part('1.2.3'); // Returns: '1'
 * $major = major_part('v2.0.0'); // Returns: '2'
 * ```
 */
function major_part(string $version): string
{
    return major($version);
}

