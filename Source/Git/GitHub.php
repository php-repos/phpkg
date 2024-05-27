<?php

namespace Phpkg\Git\GitHub;

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
 * Send an HTTP GET request to the GitHub API and return the response as an array.
 *
 * @param string $api_sub_url The API sub-URL to request.
 * @param string|null $token The GitHub auth token.
 * @return array The JSON response as an array.
 * @throws Exception If there's a network or API error.
 * @throws InvalidTokenException If the GitHub token is not valid.
 */
function get_json(string $api_sub_url, ?string $token = null): array
{
    $ch = curl_init(GITHUB_API_URL . $api_sub_url);
    curl_setopt($ch, CURLOPT_USERAGENT, 'phpkg');
    $headers = ['accept_type' => "Accept: application/vnd.github+json"];
    $headers = $token ? $headers + ['authentication' => "Authorization: Bearer $token"] : $headers;
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_values($headers));
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
 * Check if a GitHub repository has any tag.
 *
 * @param string $owner The owner (username or organization) of the repository.
 * @param string $repo The name of the repository.
 * @param string|null $token The GitHub auth token.
 * @return bool True if the repository has a tag, false otherwise.
 * @throws Exception If there's a network or API error.
 * @throws InvalidTokenException If the GitHub token is not valid.
 */
function has_any_tag(string $owner, string $repo, ?string $token = null): bool
{
    $json = get_json("repos/$owner/$repo/tags", $token);

    return isset($json[0]['name']);
}

/**
 * Find the latest version (tag) of a GitHub repository.
 *
 * @param string $owner The owner (username or organization) of the repository.
 * @param string $repo The name of the repository.
 * @param string|null $token The GitHub auth token.
 * @return string The latest version (tag) of the repository.
 * @throws Exception If there's a network or API error.
 */
function find_latest_version(string $owner, string $repo, string $token = null): string
{
    $json = get_json("repos/$owner/$repo/tags", $token);

    return $json[0]['name'];
}

/**
 * Find the hash (SHA) of a specific version (tag) of a GitHub repository.
 *
 * @param string $owner The owner (username or organization) of the repository.
 * @param string $repo The name of the repository.
 * @param string $version The version (tag) to find.
 * @param string|null $token The GitHub auth token.
 * @return string The hash (SHA) of the specified version.
 * @throws Exception If there's a network or API error.
 * @throws InvalidTokenException If the GitHub token is not valid.
 */
function find_version_hash(string $owner, string $repo, string $version, ?string $token = null): string
{
    $json = get_json("repos/$owner/$repo/git/ref/tags/$version", $token);
    if ($json['object']['type'] === 'commit') {
        return $json['object']['sha'];
    }

    $annotated_tag_hash = $json['object']['sha'];

    $json = get_json("repos/$owner/$repo/git/tags/$annotated_tag_hash", $token);

    return $json['object']['sha'];
}

/**
 * Find the hash (SHA) of the latest commit in a GitHub repository.
 *
 * @param string $owner The owner (username or organization) of the repository.
 * @param string $repo The name of the repository.
 * @param string|null $token The GitHub auth token.
 * @return string The hash (SHA) of the latest commit.
 * @throws Exception If there's a network or API error.
 * @throws InvalidTokenException If the GitHub token is not valid.
 */
function find_latest_commit_hash(string $owner, string $repo, ?string $token = null): string
{
    $json = get_json("repos/$owner/$repo/commits", $token);

    return $json[0]['sha'];
}

/**
 * Download a specific version (tag) of a GitHub repository as a zip file.
 *
 * @param string $destination The destination directory to save the zip file.
 * @param string $owner The owner (username or organization) of the repository.
 * @param string $repo The name of the repository.
 * @param string $hash The hash (SHA) of the version to download.
 * @param string|null $token The GitHub auth token.
 * @return bool True if the download and extraction were successful, false otherwise.
 * @throws Exception
 */
function download(string $destination, string $owner, string $repo, string $hash, ?string $token = null): bool
{
    $zip_file = Path::from_string($destination)->append("$hash.zip");

    $fp = fopen ($zip_file, 'w+');
    $ch = curl_init(GITHUB_URL . "$owner/$repo/zipball/$hash");
    curl_setopt($ch, CURLOPT_TIMEOUT, 600);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $headers = [];
    $headers = $token ? $headers + ['authentication' => "Authorization: Bearer $token"] : $headers;
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_values($headers));

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

/**
 * @param string $owner The owner (username or organization) of the repository.
 * @param string $repo The name of the repository.
 * @param string $hash The hash (SHA) of the version to download.
 * @param string $path THe path to check for a file
 * @param string|null $token
 * @return bool
 * @throws Exception
 */
function file_exists(string $owner, string $repo, string $hash, string $path, ?string $token = null): bool
{
    $ch = curl_init(GITHUB_API_URL . "repos/$owner/$repo/contents/$path?ref=$hash");
    curl_setopt($ch, CURLOPT_USERAGENT, 'phpkg');
    $headers = ['accept_type' => "Accept: application/vnd.github+json"];
    $headers = $token ? $headers + ['authentication' => "Authorization: Bearer $token"] : $headers;
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_values($headers));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    // Check if the request was successful
    if ($response === false) {
        throw new Exception('Error checking file existence on GitHub ' . $path);
    }

    // Decode the JSON response
    $data = json_decode($response, true);

    // Check if the JSON decoding was successful
    if ($data === null) {
        throw new Exception('Error decoding JSON response ' . $path);
    }

    // Check if the response contains a "message" field
    if (isset($data['message'])) {
        // If "message" field exists, it means the file does not exist at the given commit hash
        return false;
    }

    // If "message" field does not exist, it means the file exists at the given commit hash
    return true;
}

/**
 * @param string $owner
 * @param string $repo
 * @param string $hash
 * @param string $path
 * @param string|null $token The GitHub auth token.
 * @return string
 * @throws Exception
 */
function file_content(string $owner, string $repo, string $hash, string $path, ?string $token = null): string
{
    $ch = curl_init(GITHUB_API_URL . "repos/$owner/$repo/contents/$path?ref=$hash");
    curl_setopt($ch, CURLOPT_USERAGENT, 'phpkg');
    $headers = ['accept_type' => "Accept: application/vnd.github+json"];
    $headers = $token ? $headers + ['authentication' => "Authorization: Bearer $token"] : $headers;
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_values($headers));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        throw new Exception('Can not get file content ' . $path);
    }

    $data = json_decode($response, true);

    if ($data === null) {
        throw new Exception('Error decoding JSON response to get file content ' . $path);
    }

    if (!isset($data['content'])) {
        throw new Exception('File content not found in GitHub response for file ' . $path);
    }

    return base64_decode($data['content']);
}

/**
 * @param string $owner
 * @param string $repo
 * @param string|null $token The GitHub auth token.
 * @return array
 */
function tags(string $owner, string $repo, ?string $token = null): array
{
    static $cache = [];

    if (isset($cache[$owner][$repo])) {
        return $cache[$owner][$repo];
    }

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, "https://api.github.com/repos/$owner/$repo/tags?per_page=100");
    curl_setopt($curl, CURLOPT_HEADER, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_USERAGENT, 'phpkg');
    $headers = ['accept_type' => "Accept: application/vnd.github+json"];
    $headers = $token ? $headers + ['authentication' => "Authorization: Bearer $token"] : $headers;
    curl_setopt($curl, CURLOPT_HTTPHEADER, array_values($headers));
    $response = curl_exec($curl);
    $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    curl_close($curl);

    $response = ['header' => $header, 'body' => json_decode($body, true)];

    $tags = $response['body'];

    while (($response = next_page($response, $token)) !== null) {
        $tags = array_merge($tags, $response['body']);
    }

    $cache[$owner][$repo] = $tags;

    return $tags;
}

/**
 * @param array $previous_response
 * @param string|null $token The GitHub auth token.
 * @return array|null
 */
function next_page(array $previous_response, ?string $token = null): ?array
{
    $headers = explode("\r\n", $previous_response['header']);
    $next_url = null;

    foreach ($headers as $header) {
        if (str_starts_with($header, 'link:')) {
            // Extract the URL for rel="next"
            preg_match('/<([^>]+)>; rel="next"/', $header, $matches);
            $next_url = $matches[1] ?? null;
            break;
        }
    }

    if (is_null($next_url)) {
        return null;
    }

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $next_url);
    curl_setopt($curl, CURLOPT_HEADER, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_USERAGENT, 'phpkg');
    $headers = ['accept_type' => "Accept: application/vnd.github+json"];
    $headers = $token ? $headers + ['authentication' => "Authorization: Bearer $token"] : $headers;
    curl_setopt($curl, CURLOPT_HTTPHEADER, array_values($headers));
    $response = curl_exec($curl);
    $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    curl_close($curl);

    return ['header' => $header, 'body' => json_decode($body, true)];
}
