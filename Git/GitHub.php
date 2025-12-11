<?php

namespace PhpRepos\Git\GitHub;

use PhpRepos\Git\Exception\ApiRequestException;
use PhpRepos\Git\Exception\InvalidTokenException;
use PhpRepos\Git\Exception\NotFoundException;
use PhpRepos\Git\Exception\RateLimitedException;
use PhpRepos\Git\Http\Conversation;
use PhpRepos\Git\Http\Request;
use PhpRepos\Git\Http\Request\Header as RequestHeader;
use PhpRepos\Git\Http\Request\Url;
use PhpRepos\Git\Http\Response\Header as ResponseHeader;
use PhpRepos\Git\Http\Response;
use PhpRepos\Git\Http\Response\Status;
use PhpRepos\Git\Signals\SendingGitHttpRequest;
use PhpRepos\Git\Signals\HttpResponseReceived;
use PhpRepos\Git\Signals\GitHostDownloadProgress;
use PhpRepos\Observer\Observer;
use function PhpRepos\Observer\Observer\broadcast;
use function PhpRepos\Datatype\Arr\any;
use function PhpRepos\Datatype\Arr\first;
use function PhpRepos\Datatype\Arr\map;

/**
 * Send an HTTP request to the GitHub API and return the response as a conversation.
 *
 * @param Request\Message $request The request message to be sent.
 * @return Conversation The api call conversation.
 * @throws ApiRequestException If the GitHub api fails.
 * @throws InvalidTokenException If the GitHub token is not valid.
 * @throws RateLimitedException If user gets rate limited.
 * @throws NotFoundException Either the URL is not valid or user does not have access.
 */
function send(Request\Message $request): Conversation
{
    static $cache = [];
    $cache_key = md5(serialize($request));

    if (isset($cache[$cache_key])) {
        return $cache[$cache_key];
    }

    $has_token = any($request->header, fn (array $header) => $header['key'] === 'Authorization');
    Observer\propose(SendingGitHttpRequest::using($request->url, $request->method, $has_token));

    $start_time = microtime(true);

    $ch = curl_init($request->url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, map($request->header, fn (array $header) => $header['key'] . ': ' . $header['value']));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    if (PHP_OS === 'WINNT') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    }

    $output = curl_exec($ch);
    $error = curl_error($ch);

    if (curl_errno($ch) > 0) {
        $duration = microtime(true) - $start_time;
        Observer\broadcast(HttpResponseReceived::with($request->url, $request->method, 0, $duration));
        throw new ApiRequestException('Git curl error: ' . $error);
    }

    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($output, 0, $header_size);
    $body = substr($output, $header_size);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $status_code = Status::tryFrom($http_code);

    $duration = microtime(true) - $start_time;
    Observer\broadcast(HttpResponseReceived::with($request->url, $request->method, $http_code, $duration));

    $header_lines = explode("\r\n", trim($headers));
    $response_header = new ResponseHeader();
    foreach ($header_lines as $header_line) {
        if (str_contains($header_line, ':')) {
            list($key, $value) = explode(':', $header_line, 2);
            $response_header->put(trim($key), trim($value));
        }
    }

    if ($status_code === Status::UNAUTHORIZED) {
        throw new InvalidTokenException('GitHub token is not valid.');
    }

    if (($status_code === Status::FORBIDDEN || $status_code === Status::TOO_MANY_REQUESTS) && any($response_header, fn (array $header) => $header['key'] === 'x-ratelimit-remaining' && $header['value'] == 0)) {
        $reset_time = first($response_header, fn (array $header) => $header['key'] === 'x-ratelimit-reset')['value'] - time();
        throw new RateLimitedException("You have reached the GitHub API rate limit. Please try again in $reset_time seconds.");
    }

    if ($status_code === Status::NOT_FOUND) {
        any($request->header, fn (array $header) => $header['key'] === 'Authorization')
            ? throw new NotFoundException('The endpoint not found.')
            : throw new NotFoundException('The endpoint not found. If it is a private repository, please provide a token.');
    }

    $response = new Response\Message($status_code, $response_header, new Response\Body($body));

    $conversation = new Conversation($request, $response);

    $cache[$cache_key] = $conversation;

    return $conversation;
}

/**
 * Send an HTTP GET request to the GitHub API and return the response as a conversation.
 *
 * @param string $api_sub_url The API sub-URL to request.
 * @param Request\Header $request_header The request header object.
 * @param string|null $token The GitHub auth token.
 * @return Conversation The api call conversation.
 * @throws ApiRequestException If the GitHub api fails.
 * @throws InvalidTokenException If the GitHub token is not valid.
 * @throws RateLimitedException If user gets rate limited.
 * @throws NotFoundException Either the URL is not valid or user does not have access.
 */
function get(string $api_sub_url, Request\Header $request_header, ?string $token): Conversation
{
    $request_header = Request\Headers\user_agent($request_header, 'phpkg');

    if ($token !== null) {
        $request_header = Request\Headers\authorization($request_header, "Bearer $token");
    }

    $request = new Request\Message(new Url('https://api.github.com/' . $api_sub_url), 'GET', $request_header);

    return send($request);
}

/**
 * Send an HTTP GET request to the GitHub API and return the response as an array.
 *
 * @param string $api_sub_url The API sub-URL to request.
 * @param string|null $token The GitHub auth token.
 * @return Conversation The http conversation
 * @throws ApiRequestException If the GitHub api fails.
 * @throws InvalidTokenException If the GitHub token is not valid.
 * @throws RateLimitedException If user gets rate limited.
 * @throws NotFoundException Either the URL is not valid or user does not have access.
 */
function get_json(string $api_sub_url, ?string $token): Conversation
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
 * @throws ApiRequestException If the GitHub api fails.
 * @throws InvalidTokenException If the GitHub token is not valid.
 * @throws RateLimitedException If user gets rate limited.
 * @throws NotFoundException Either the URL is not valid or user does not have access.
 */
function has_any_tag(string $owner, string $repo, ?string $token): bool
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
 * @throws ApiRequestException If the GitHub api fails.
 * @throws InvalidTokenException If the GitHub token is not valid.
 * @throws RateLimitedException If user gets rate limited.
 * @throws NotFoundException Either the URL is not valid or user does not have access.
 */
function find_latest_version(string $owner, string $repo, ?string $token): string
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
 * @throws ApiRequestException If the GitHub api fails.
 * @throws InvalidTokenException If the GitHub token is not valid.
 * @throws RateLimitedException If user gets rate limited.
 * @throws NotFoundException Either the URL is not valid or user does not have access.
 */
function find_version_hash(string $owner, string $repo, string $version, ?string $token): string
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
 * @throws ApiRequestException If the GitHub api fails.
 * @throws InvalidTokenException If the GitHub token is not valid.
 * @throws RateLimitedException If user gets rate limited.
 * @throws NotFoundException Either the URL is not valid or user does not have access.
 */
function find_latest_commit_hash(string $owner, string $repo, ?string $token): string
{
    $response = get_json("repos/$owner/$repo/commits", $token)->response;

    return Response\Responses\to_array($response)[0]['sha'];
}

/**
 * Download an archive from GitHub for the given repository as a zip file.
 *
 * @param string $owner The owner (username or organization) of the repository.
 * @param string $repo The name of the repository.
 * @param string $hash The hash (SHA) of the version to download.
 * @param string|null $token The GitHub auth token.
 * @param string $destination The destination directory to save the zip file.
 * @return bool True if the download was successful, false otherwise.
 */
function download_archive(string $owner, string $repo, string $hash, ?string $token, string $destination): bool
{
    $fp = fopen ($destination, 'w+');
    $ch = curl_init("https://github.com/$owner/$repo/zipball/$hash");
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $headers = $token ? ["Authorization: Bearer $token"] : [];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if (PHP_OS === 'WINNT') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    }

    // Enable progress tracking
    curl_setopt($ch, CURLOPT_NOPROGRESS, false);
    
    // Track start time for calculating elapsed time
    $download_start_time = microtime(true);
    $final_download_size = -1;
    $final_downloaded = 0;
    
    // Use PROGRESS callback to track download progress
    curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function ($resource, $download_size, $downloaded, $upload_size, $uploaded) use ($owner, $repo, $hash, $destination, $download_start_time, &$final_download_size, &$final_downloaded) {
        $elapsed_seconds = microtime(true) - $download_start_time;
        $final_download_size = $download_size;
        $final_downloaded = $downloaded;
        Observer\share(GitHostDownloadProgress::from_github_downloader(
            'github.com',
            $owner,
            $repo,
            $hash,
            $download_size,
            $downloaded,
            $upload_size,
            $uploaded,
            $elapsed_seconds,
            $destination
        ));
        return 0; // Return 0 to continue download
    });

    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Send final progress update to ensure completion is shown
    // This handles cases where the callback doesn't fire at completion
    if ($http_code === 200) {
        $elapsed_seconds = microtime(true) - $download_start_time;
        $file_size = \file_exists($destination) ? \filesize($destination) : $final_downloaded;
        $total_size = $final_download_size > 0 ? $final_download_size : $file_size;
        Observer\share(GitHostDownloadProgress::from_github_downloader(
            'github.com',
            $owner,
            $repo,
            $hash,
            $total_size,
            $file_size,
            0,
            0,
            $elapsed_seconds,
            $destination
        ));
    }

    return fclose($fp);
}

/**
 * Check if a file exists at a specific path in a GitHub repository.
 *
 * @param string $owner The owner (username or organization) of the repository.
 * @param string $repo The name of the repository.
 * @param string $hash The hash (SHA) of the version to check.
 * @param string|null $token The GitHub auth token.
 * @param string $path The path to check for a file.
 * @return bool True if the file exists, false otherwise.
 * @throws ApiRequestException If the GitHub api fails.
 * @throws InvalidTokenException If the GitHub token is not valid.
 * @throws RateLimitedException If user gets rate limited.
 */
function file_exists(string $owner, string $repo, string $hash, ?string $token, string $path): bool
{
    try {
        $response = get_json("repos/$owner/$repo/contents/$path?ref=$hash", $token)->response;

        return isset(Response\Responses\to_array($response)['content']);
    } catch (NotFoundException) {
        return false;
    }
}

/**
 * Get the content of a file from a GitHub repository.
 *
 * @param string $owner The owner (username or organization) of the repository.
 * @param string $repo The name of the repository.
 * @param string $hash The hash (SHA) of the version to get.
 * @param string|null $token The GitHub auth token.
 * @param string $path The path to the file.
 * @return string The decoded content of the file.
 * @throws ApiRequestException If the GitHub api fails.
 * @throws InvalidTokenException If the GitHub token is not valid.
 * @throws RateLimitedException If user gets rate limited.
 * @throws NotFoundException Either the URL is not valid or user does not have access.
 */
function file_content(string $owner, string $repo, string $hash, ?string $token, string $path): string
{
    $response = get_json("repos/$owner/$repo/contents/$path?ref=$hash", $token)->response;

    return base64_decode(Response\Responses\to_array($response)['content']);
}

/**
 * Get all tags from a GitHub repository.
 *
 * @param string $owner The owner (username or organization) of the repository.
 * @param string $repo The name of the repository.
 * @param string|null $token The GitHub auth token.
 * @return array Array of tag data from the repository.
 * @throws ApiRequestException If the GitHub api fails.
 * @throws InvalidTokenException If the GitHub token is not valid.
 * @throws RateLimitedException If user gets rate limited.
 * @throws NotFoundException Either the URL is not valid or user does not have access.
 */
function tags(string $owner, string $repo, ?string $token): array
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
 * Get the next page of results from a paginated GitHub API response.
 *
 * @param Conversation $conversation The current conversation object.
 * @return Conversation|null The next page conversation or null if no more pages.
 * @throws ApiRequestException If the GitHub api fails.
 * @throws InvalidTokenException If the GitHub token is not valid.
 * @throws RateLimitedException If user gets rate limited.
 * @throws NotFoundException Either the URL is not valid or user does not have access.
 */
function next_page(Conversation $conversation): ?Conversation
{
    $link = first($conversation->response->header, fn (array $header) => $header['key'] === 'link');

    if (! $link) {
        return null;
    }

    preg_match('/<([^>]+)>; rel="next"/', $link['value'], $matches);
    if (! isset($matches[1])) {
        // This is the last page
        return null;
    }
    $next_url = $matches[1];
    $request = new Request\Message(new Url($next_url), 'GET', $conversation->request->header);

    return send($request);
}
