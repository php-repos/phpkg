<?php

namespace Phpkg\Providers\GitHub;

use Phpkg\Git\Exception\InvalidTokenException;
use ZipArchive;
use function PhpRepos\FileManager\Directory\delete_recursive;
use function PhpRepos\FileManager\Directory\ls;
use function PhpRepos\FileManager\Directory\preserve_copy_recursively;
use function PhpRepos\FileManager\Directory\renew_recursive;
use function PhpRepos\FileManager\File\delete;
use function PhpRepos\FileManager\Resolver\realpath;

const GITHUB_DOMAIN = 'github.com';
const GITHUB_URL = 'https://github.com/';
const GITHUB_API_URL = 'https://api.github.com/';
const GITHUB_SSH_URL = 'git@github.com:';

function is_ssh(string $package_url): bool
{
    return str_starts_with($package_url, 'git@');
}

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

function github_token(?string $token = null): string
{
    if (! is_null($token)) {
        putenv('GITHUB_TOKEN=' . $token);
    }

    return getenv('GITHUB_TOKEN', true);
}

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
    $output = curl_exec($ch);
    curl_close($ch);

    $response = json_decode($output, true);

    if (isset($response['message']) && $response['message'] === 'Bad credentials') {
        throw new InvalidTokenException('GitHub token is not valid.');
    }

    return $response;
}

function has_release(string $owner, string $repo): bool
{
    $json = get_json("repos/$owner/$repo/releases/latest");

    return isset($json['tag_name']);
}

function find_latest_version(string $owner, string $repo): string
{
    $json = get_json("repos/$owner/$repo/releases/latest");

    return $json['tag_name'];
}

function find_version_hash(string $owner, string $repo, string $version): string
{
    $json = get_json("repos/$owner/$repo/git/ref/tags/$version");

    return $json['object']['sha'];
}

function find_latest_commit_hash(string $owner, string $repo): string
{
    $json = get_json("repos/$owner/$repo/commits");

    return $json[0]['sha'];
}

function download(string $destination, string $owner, string $repo, string $version): bool
{
    $token = github_token();
    $temp = realpath(sys_get_temp_dir(). "/phpkg/installer/cache/$owner/$repo/") . DIRECTORY_SEPARATOR;

    renew_recursive($temp);

    $zip_file = $temp . $repo . '.zip';

    $fp = fopen ($zip_file, 'w+');
    $ch = curl_init(GITHUB_URL . "$owner/$repo/zipball/$version");
    curl_setopt($ch, CURLOPT_TIMEOUT, 600);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);

    $zip = new ZipArchive;
    $res = $zip->open($zip_file);

    if ($res === TRUE) {
        $zip->extractTo($temp);
        $zip->close();
    } else {
        var_dump($res);
    }

    delete($zip_file);

    $files = ls($temp);
    $directory = array_reduce($files, function ($carry, $filename) use ($owner, $repo) {
        return str_starts_with($filename, "$owner-$repo-") ? $filename : $carry;
    });
    $unzip_directory = $temp . $directory;

    renew_recursive($destination);

    return  preserve_copy_recursively($unzip_directory, $destination) && delete_recursive($unzip_directory);
}

function clone_to(string $destination, string $owner, string $repo): bool
{
    $github_ssh_url = GITHUB_SSH_URL;
    $output = passthru("git clone $github_ssh_url$owner/$repo.git $destination");

    return  $output === null;
}
