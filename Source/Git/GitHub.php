<?php

namespace Phpkg\Git\GitHub;

use Exception;
use Phpkg\Git\Exception\GithubApiRequestException;
use Phpkg\Git\Exception\InvalidTokenException;
use Phpkg\Git\Exception\NotFoundException;
use Phpkg\Git\Exception\RateLimitedException;
use Phpkg\Git\Exception\UnauthenticatedRateLimitedException;
use Phpkg\Http\Conversation;
use Phpkg\Http\Request;
use Phpkg\Http\Request\Header as RequestHeader;
use Phpkg\Http\Request\Method;
use Phpkg\Http\Request\Url;
use Phpkg\Http\Response\Header as ResponseHeader;
use Phpkg\Http\Response;
use Phpkg\Http\Response\Status;
use PhpRepos\Datatype\Pair;
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
 * Send an HTTP request to the GitHub API and return the response as a conversation.
 *
 * @param Request\Message $request The request message to be sent.
 * @return Conversation The api call conversation.
 * @throws GithubApiRequestException If the GitHub api fails.
 * @throws InvalidTokenException If the GitHub token is not valid.
 * @throws RateLimitedException If user gets rate limited.
 * @throws UnauthenticatedRateLimitedException If unauthenticated request gets rate limited.
 * @throws NotFoundException Either the URL is not valid or user does not have access.
 */
function send(Request\Message $request): Conversation
{
    static $cache = [];

    if (isset($cache[$request->url->string()])) {
        return $cache[$request->url->string()];
    }

    $ch = curl_init($request->url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, Request\Requests\header_to_array($request));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    if (is_windows()) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    }

    $output = curl_exec($ch);
    $error = curl_error($ch);

    if (curl_errno($ch) > 0) {
        curl_close($ch);
        throw new GithubApiRequestException('Git curl error: ' . $error);
    }

    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($output, 0, $header_size);
    $body = substr($output, $header_size);
    $status_code = Status::tryFrom(curl_getinfo($ch, CURLINFO_HTTP_CODE));

    curl_close($ch);

    $header_lines = explode("\r\n", trim($headers));
    $response_header = new ResponseHeader();
    foreach ($header_lines as $header_line) {
        if (str_contains($header_line, ':')) {
            list($key, $value) = explode(': ', $header_line, 2);
            $response_header->push(new Pair($key, $value));
        }
    }

    if ($status_code === Status::UNAUTHORIZED) {
        throw new InvalidTokenException('GitHub token is not valid.');
    }

    if (($status_code === Status::FORBIDDEN || $status_code === Status::TOO_MANY_REQUESTS) && $response_header->first(fn (Pair $header) => $header->key === 'x-ratelimit-remaining')->value == 0) {
        Request\Requests\has_authorization($request)
            ? throw new RateLimitedException('You have reached the GitHub API rate limit. Please try again in ' . time() - $response_header->first(fn (Pair $header) => $header->key === 'x-ratelimit-reset')->value)
            : throw new UnauthenticatedRateLimitedException('You have reached the GitHub API rate limit. Please add a token to your request and try again.');
    }

    if ($status_code === Status::NOT_FOUND) {
        Request\Requests\has_authorization($request)
            ? throw new NotFoundException('The endpoint not found.')
            : throw new NotFoundException('The endpoint not found. If it is a private repository, please provide a token.');
    }

    $response = new Response\Message($status_code, $response_header, new Response\Body($body));

    $conversation = new Conversation($request, $response);

    $cache[md5(serialize($request))] = $conversation;

    return $conversation;
}

/**
 * Send an HTTP GET request to the GitHub API and return the response as a conversation.
 *
 * @param string $api_sub_url The API sub-URL to request.
 * @param Request\Header $request_header The request header object.
 * @param string|null $token The GitHub auth token.
 * @return Conversation The api call conversation.
 * @throws GithubApiRequestException If the GitHub api fails.
 * @throws InvalidTokenException If the GitHub token is not valid.
 * @throws RateLimitedException If user gets rate limited.
 * @throws UnauthenticatedRateLimitedException If unauthenticated request gets rate limited.
 * @throws NotFoundException Either the URL is not valid or user does not have access.
 */
function get(string $api_sub_url, Request\Header $request_header, ?string $token = null): Conversation
{
    $request_header = Request\Headers\user_agent($request_header, 'phpkg');

    if ($token !== null) {
        $request_header = Request\Headers\authorization($request_header, "Bearer $token");
    }

    $request = new Request\Message(new Url(GITHUB_API_URL . $api_sub_url), Method::GET, $request_header);

    return send($request);
}

/**
 * Send an HTTP GET request to the GitHub API and return the response as an array.
 *
 * @param string $api_sub_url The API sub-URL to request.
 * @param string|null $token The GitHub auth token.
 * @return Conversation The http conversation
 * @throws GithubApiRequestException If the GitHub api fails.
 * @throws InvalidTokenException If the GitHub token is not valid.
 * @throws RateLimitedException If user gets rate limited.
 * @throws UnauthenticatedRateLimitedException If unauthenticated request gets rate limited.
 * @throws NotFoundException Either the URL is not valid or user does not have access.
 */
function get_json(string $api_sub_url, ?string $token = null): Conversation
{
    return get($api_sub_url, Request\Headers\accept(new RequestHeader(), 'application/vnd.github+json'), $token);
}

/**
 * Check if a GitHub repository has any tag.
 *
 * @param string $owner The owner (username or organization) of the repository.
 * @param string $repo The name of the repository.
 * @param string|null $token The GitHub auth token.
 * @return bool True if the repository has a tag, false otherwise.
 * @throws Exception If there's a network or API error.
 * @throws GithubApiRequestException If the GitHub api fails.
 * @throws InvalidTokenException If the GitHub token is not valid.
 * @throws RateLimitedException If user gets rate limited.
 * @throws UnauthenticatedRateLimitedException If unauthenticated request gets rate limited.
 * @throws NotFoundException Either the URL is not valid or user does not have access.
 */
function has_any_tag(string $owner, string $repo, ?string $token = null): bool
{
    $response = get_json("repos/$owner/$repo/tags", $token)->response;

    return isset(Response\Responses\to_array($response)[0]['name']);
}

/**
 * Find the latest version (tag) of a GitHub repository.
 *
 * @param string $owner The owner (username or organization) of the repository.
 * @param string $repo The name of the repository.
 * @param string|null $token The GitHub auth token.
 * @return string The latest version (tag) of the repository.
 * @throws GithubApiRequestException If the GitHub api fails.
 * @throws InvalidTokenException If the GitHub token is not valid.
 * @throws RateLimitedException If user gets rate limited.
 * @throws UnauthenticatedRateLimitedException If unauthenticated request gets rate limited.
 * @throws NotFoundException Either the URL is not valid or user does not have access.
 */
function find_latest_version(string $owner, string $repo, string $token = null): string
{
    $response = get_json("repos/$owner/$repo/tags", $token)->response;

    return Response\Responses\to_array($response)[0]['name'];
}

/**
 * Find the hash (SHA) of a specific version (tag) of a GitHub repository.
 *
 * @param string $owner The owner (username or organization) of the repository.
 * @param string $repo The name of the repository.
 * @param string $version The version (tag) to find.
 * @param string|null $token The GitHub auth token.
 * @return string The hash (SHA) of the specified version.
 * @throws GithubApiRequestException If the GitHub api fails.
 * @throws InvalidTokenException If the GitHub token is not valid.
 * @throws RateLimitedException If user gets rate limited.
 * @throws UnauthenticatedRateLimitedException If unauthenticated request gets rate limited.
 * @throws NotFoundException Either the URL is not valid or user does not have access.
 */
function find_version_hash(string $owner, string $repo, string $version, ?string $token = null): string
{
    $response = get_json("repos/$owner/$repo/git/ref/tags/$version", $token)->response;
    $json = Response\Responses\to_array($response);

    if ($json['object']['type'] === 'commit') {
        return $json['object']['sha'];
    }

    $annotated_tag_hash = $json['object']['sha'];

    $response = get_json("repos/$owner/$repo/git/tags/$annotated_tag_hash", $token)->response;
    $json = Response\Responses\to_array($response);

    return $json['object']['sha'];
}

/**
 * Find the hash (SHA) of the latest commit in a GitHub repository.
 *
 * @param string $owner The owner (username or organization) of the repository.
 * @param string $repo The name of the repository.
 * @param string|null $token The GitHub auth token.
 * @return string The hash (SHA) of the latest commit.
 * @throws GithubApiRequestException If the GitHub api fails.
 * @throws InvalidTokenException If the GitHub token is not valid.
 * @throws RateLimitedException If user gets rate limited.
 * @throws UnauthenticatedRateLimitedException If unauthenticated request gets rate limited.
 * @throws NotFoundException Either the URL is not valid or user does not have access.
 */
function find_latest_commit_hash(string $owner, string $repo, ?string $token = null): string
{
    $response = get_json("repos/$owner/$repo/commits", $token)->response;

    return Response\Responses\to_array($response)[0]['sha'];
}

/**
 * Download an archive from GitHub for the given repository as a zip file.
 *
 * @param string $destination The destination directory to save the zip file.
 * @param string $owner The owner (username or organization) of the repository.
 * @param string $repo The name of the repository.
 * @param string $hash The hash (SHA) of the version to download.
 * @param string|null $token The GitHub auth token.
 * @return bool True if the download and extraction were successful, false otherwise.
 * @throws Exception
 */
function download_archive(string $destination, string $owner, string $repo, string $hash, ?string $token = null): bool
{
    $zip_file = Path::from_string($destination)->append("$hash.zip");
    $fp = fopen ($zip_file, 'w+');
    $ch = curl_init(GITHUB_URL . "$owner/$repo/zipball/$hash");
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
 * @throws GithubApiRequestException If the GitHub api fails.
 * @throws InvalidTokenException If the GitHub token is not valid.
 * @throws RateLimitedException If user gets rate limited.
 * @throws UnauthenticatedRateLimitedException If unauthenticated request gets rate limited.
 */
function file_exists(string $owner, string $repo, string $hash, string $path, ?string $token = null): bool
{
    try {
        $response = get_json("repos/$owner/$repo/contents/$path?ref=$hash", $token)->response;

        return isset(Response\Responses\to_array($response)['content']);
    } catch (NotFoundException) {
        return false;
    }
}

/**
 * @param string $owner
 * @param string $repo
 * @param string $hash
 * @param string $path
 * @param string|null $token The GitHub auth token.
 * @return string
 * @throws GithubApiRequestException If the GitHub api fails.
 * @throws InvalidTokenException If the GitHub token is not valid.
 * @throws RateLimitedException If user gets rate limited.
 * @throws UnauthenticatedRateLimitedException If unauthenticated request gets rate limited.
 * @throws NotFoundException Either the URL is not valid or user does not have access.
 */
function file_content(string $owner, string $repo, string $hash, string $path, ?string $token = null): string
{
    $response = get_json("repos/$owner/$repo/contents/$path?ref=$hash", $token)->response;

    return base64_decode(Response\Responses\to_array($response)['content']);
}

/**
 * @param string $owner
 * @param string $repo
 * @param string|null $token The GitHub auth token.
 * @return array
 * @throws GithubApiRequestException If the GitHub api fails.
 * @throws InvalidTokenException If the GitHub token is not valid.
 * @throws RateLimitedException If user gets rate limited.
 * @throws UnauthenticatedRateLimitedException If unauthenticated request gets rate limited.
 * @throws NotFoundException Either the URL is not valid or user does not have access.
 */
function tags(string $owner, string $repo, ?string $token = null): array
{
    $conversation = get_json("repos/$owner/$repo/tags?per_page=100", $token);

    $tags = [];

    while ($conversation) {
        $tags[] = Response\Responses\to_array($conversation->response);
        $conversation = next_page($conversation);
    }

    return call_user_func_array('array_merge', $tags);
}

/**
 * @param Conversation $conversation
 * @return Conversation|null
 * @throws GithubApiRequestException If the GitHub api fails.
 * @throws InvalidTokenException If the GitHub token is not valid.
 * @throws RateLimitedException If user gets rate limited.
 * @throws UnauthenticatedRateLimitedException If unauthenticated request gets rate limited.
 * @throws NotFoundException Either the URL is not valid or user does not have access.
 */
function next_page(Conversation $conversation): ?Conversation
{
    $link = $conversation->response->header->first(fn (Pair $header) => $header->key === 'link');

    if (! $link) {
        return null;
    }

    preg_match('/<([^>]+)>; rel="next"/', $link->value, $matches);
    if (! isset($matches[1])) {
        // This is the last page
        return null;
    }
    $next_url = $matches[1];
    $request = new Request\Message(new Url($next_url), Method::GET, $conversation->request->header);

    return send($request);
}
