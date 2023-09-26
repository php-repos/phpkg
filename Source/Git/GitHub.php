<?php

namespace Phpkg\Providers\GitHub;

use Exception;
use Phpkg\Git\Exception\InvalidTokenException;
use PhpRepos\FileManager\Path;
use ZipArchive;
use function Phpkg\System\is_windows;
use function PhpRepos\FileManager\Directory\delete_recursive;
use function PhpRepos\FileManager\Directory\ls;
use function PhpRepos\FileManager\Directory\preserve_copy_recursively;
use function PhpRepos\FileManager\File\delete;

// Constants for GitHub-related URLs and domain information
const GITHUB_DOMAIN = 'github.com';
const GITHUB_URL = 'https://github.com/';
const GITHUB_API_URL = 'https://api.github.com/';
const GITHUB_SSH_URL = 'git@github.com:';

/**
 * Check if a package URL is in SSH format.
 *
 * @param string $package_url The package URL to check.
 * @return bool True if the package URL is in SSH format, false otherwise.
 */
function is_ssh(string $package_url): bool
{
    return str_starts_with($package_url, 'git@');
}

/**
 * Extract the owner (username or organization) from a GitHub repository URL.
 *
 * @param string $package_url The GitHub repository URL.
 * @return string The owner (username or organization).
 */
function extract_owner(string $package_url): string
{
    if (is_ssh($package_url)) {
        $owner_and_repo = str_replace(GITHUB_SSH_URL, '', $package_url);
    } else {
        $owner_and_repo = str_replace(GITHUB_URL, '', $package_url);
    }

    if (str_ends_with($owner_and_repo, '.git')) {
        $owner_and_repo = substr_replace($owner_and_repo, '', -4);
    }

    return explode('/', $owner_and_repo)[0];
}

/**
 * Extract the repository name from a GitHub repository URL.
 *
 * @param string $package_url The GitHub repository URL.
 * @return string The repository name.
 */
function extract_repo(string $package_url): string
{
    if (is_ssh($package_url)) {
        $owner_and_repo = str_replace(GITHUB_SSH_URL, '', $package_url);
    } else {
        $owner_and_repo = str_replace(GITHUB_URL, '', $package_url);
    }

    if (str_ends_with($owner_and_repo, '.git')) {
        $owner_and_repo = substr_replace($owner_and_repo, '', -4);
    }

    return explode('/', $owner_and_repo)[1];
}

/**
 * Retrieve or set the GitHub token used for authentication in API requests.
 *
 * @param string|null $token (Optional) The GitHub token to set.
 * @return string The GitHub token.
 */
function github_token(?string $token = null): string
{
    if (! is_null($token)) {
        putenv('GITHUB_TOKEN=' . $token);
    }

    return getenv('GITHUB_TOKEN', true);
}

/**
 * Send an HTTP GET request to the GitHub API and return the response as an array.
 *
 * @param string $api_sub_url The API sub-URL to request.
 * @return array The JSON response as an array.
 * @throws Exception If there's a network or API error.
 * @throws InvalidTokenException If the GitHub token is not valid.
 */
function get_json(string $api_sub_url): array
{
    $token = github_token();

    $ch = curl_init(GITHUB_API_URL . $api_sub_url);
    curl_setopt($ch, CURLOPT_USERAGENT, 'phpkg');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Accept: application/vnd.github+json",
        "Authorization: Bearer $token",
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    if (is_windows()) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    }

    $output = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if (curl_errno($ch) > 0) {
        throw new Exception('Git curl error: ' . $error);
    }

    $response = json_decode($output, true);

    if (isset($response['message']) && $response['message'] === 'Bad credentials') {
        throw new InvalidTokenException('GitHub token is not valid.');
    }

    return $response;
}

/**
 * Check if a GitHub repository has a release.
 *
 * @param string $owner The owner (username or organization) of the repository.
 * @param string $repo The name of the repository.
 * @return bool True if the repository has a release, false otherwise.
 * @throws Exception If there's a network or API error.
 * @throws InvalidTokenException If the GitHub token is not valid.
 */
function has_release(string $owner, string $repo): bool
{
    $json = get_json("repos/$owner/$repo/releases/latest");

    return isset($json['tag_name']);
}

/**
 * Find the latest version (tag) of a GitHub repository.
 *
 * @param string $owner The owner (username or organization) of the repository.
 * @param string $repo The name of the repository.
 * @return string The latest version (tag) of the repository.
 * @throws Exception If there's a network or API error.
 * @throws InvalidTokenException If the GitHub token is not valid.
 */
function find_latest_version(string $owner, string $repo): string
{
    $json = get_json("repos/$owner/$repo/releases/latest");

    return $json['tag_name'];
}

/**
 * Find the hash (SHA) of a specific version (tag) of a GitHub repository.
 *
 * @param string $owner The owner (username or organization) of the repository.
 * @param string $repo The name of the repository.
 * @param string $version The version (tag) to find.
 * @return string The hash (SHA) of the specified version.
 * @throws Exception If there's a network or API error.
 * @throws InvalidTokenException If the GitHub token is not valid.
 */
function find_version_hash(string $owner, string $repo, string $version): string
{
    $json = get_json("repos/$owner/$repo/git/ref/tags/$version");

    return $json['object']['sha'];
}

/**
 * Find the hash (SHA) of the latest commit in a GitHub repository.
 *
 * @param string $owner The owner (username or organization) of the repository.
 * @param string $repo The name of the repository.
 * @return string The hash (SHA) of the latest commit.
 * @throws Exception If there's a network or API error.
 * @throws InvalidTokenException If the GitHub token is not valid.
 */
function find_latest_commit_hash(string $owner, string $repo): string
{
    $json = get_json("repos/$owner/$repo/commits");

    return $json[0]['sha'];
}

/**
 * Download a specific version (tag) of a GitHub repository as a zip file.
 *
 * @param string $destination The destination directory to save the zip file.
 * @param string $owner The owner (username or organization) of the repository.
 * @param string $repo The name of the repository.
 * @param string $hash The hash (SHA) of the version to download.
 * @return bool True if the download and extraction were successful, false otherwise.
 * @throws Exception
 */
function download(string $destination, string $owner, string $repo, string $hash): bool
{
    $token = github_token();

    $zip_file = Path::from_string($destination)->append("$hash.zip");

    $fp = fopen ($zip_file, 'w+');
    $ch = curl_init(GITHUB_URL . "$owner/$repo/zipball/$hash");
    curl_setopt($ch, CURLOPT_TIMEOUT, 600);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
    if (is_windows()) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    }
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);

    $zip = new ZipArchive;
    $res = $zip->open($zip_file);

    if ($res === TRUE) {
        $zip->extractTo($zip_file->parent());
        $zip->close();
    } else {
        throw new Exception('Failed to extract the archive zip file!');
    }

    delete($zip_file);

    $unzip_directory = ls($zip_file->parent())->first();

    return preserve_copy_recursively($unzip_directory, $destination) && delete_recursive($unzip_directory);
}
