<?php

namespace Phpkg\Solution\Repositories;

use Phpkg\Solution\Data\Repository;
use Phpkg\Infra\GitHosts;
use Phpkg\Infra\Strings;
use function Phpkg\Infra\Arrays\first;
use function Phpkg\Infra\Logs\debug;
use function Phpkg\Infra\Logs\log;
use function PhpRepos\Datatype\Str\before_first_occurrence;

function is_valid_package_identifier(string $url): bool
{
    log('Checking if URL is a valid package identifier', ['url' => $url]);
    return str_starts_with($url, 'git@') || str_starts_with($url, 'https://') || str_starts_with($url, 'http://');
}

function is_ssh(string $url): bool
{
    return str_starts_with($url, 'git@');
}

function extract_domain(string $url): string
{
    if (is_ssh($url)) {
        $url = str_replace('git@', '', $url);
        return before_first_occurrence($url, ':');
    }

    $parts = parse_url($url);

    return $parts['host'] ?? '';
}

function extract_owner(string $url): string
{
    if (is_ssh($url)) {
        $owner_and_repo = str_replace('git@' . extract_domain($url) . ':', '', $url);
    } else {
        $owner_and_repo = str_replace('https://' . extract_domain($url) . '/', '', $url);
    }

    if (str_ends_with($owner_and_repo, '.git')) {
        $owner_and_repo = substr_replace($owner_and_repo, '', -4);
    }

    return explode('/', $owner_and_repo)[0];
}

function extract_repo(string $url): string
{
    if (is_ssh($url)) {
        $owner_and_repo = str_replace('git@' . extract_domain($url) . ':', '', $url);
    } else {
        $owner_and_repo = str_replace('https://' . extract_domain($url) . '/', '', $url);
    }

    if (str_ends_with($owner_and_repo, '.git')) {
        $owner_and_repo = substr_replace($owner_and_repo, '', -4);
    }

    return explode('/', $owner_and_repo)[1];
}

function are_equal(Repository $a, Repository $b): bool
{
    log('Comparing two repositories for equality', [
        'repository_a' => $a->identifier(),
        'repository_b' => $b->identifier(),
    ]);
    return $a->domain === $b->domain && $a->owner === $b->owner && $a->repo === $b->repo;
}

function prepare(string $url, array $credentials): Repository
{
    log('Preparing repository data', ['url' => $url]);
    $domain = extract_domain($url);
    $owner = extract_owner($url);
    $repo = extract_repo($url);
    $token = first($credentials, fn (string $token, string $provider) => $provider === $domain) ?? null;

    return new Repository($url, $domain, $owner, $repo, $token);
}

function from(string $url): Repository
{
    debug('Creating repository from URL', ['url' => $url]);
    $domain = extract_domain($url);
    $owner = extract_owner($url);
    $repo = extract_repo($url);

    return new Repository($url, $domain, $owner, $repo, null);
}

function has_any_tag(Repository $repository): bool
{
    log('Checking if repository has any tags', [
        'repository' => $repository->identifier(),
    ]);
    return GitHosts\has_any_tag(
        $repository->domain,
        $repository->owner,
        $repository->repo,
        $repository->token
    );
}

function can_guess_a_repo(string $identifier): bool
{
    log('Checking if we can guess a repository from identifier', ['identifier' => $identifier]);
    
    $slash_count = substr_count($identifier, '/');
    
    if ($slash_count === 0) return true;
    if ($slash_count > 1) return false;
    if (strpos($identifier, '/') === 0) return false;
    if (strpos($identifier, '/') === strlen($identifier) - 1) return false;
    
    return true;
}

function guess_the_repo(string $identifier): string
{
    log('Guessing repository URL from identifier', ['identifier' => $identifier]);
    
    // If identifier contains a slash, it's in owner/repo format
    if (Strings\contains($identifier, '/')) {
        return 'https://github.com/' . $identifier . '.git';
    }
    
    // Otherwise, it's just a repo name, assume it's under php-repos
    return 'https://github.com/php-repos/' . $identifier . '.git';
}

function is_main_package(array $config, Repository $repository): bool
{
    debug('Checking if repository is the main package', [
        'repository' => $repository->identifier(),
        'config' => $config,
    ]);

    foreach ($config['packages'] as $package_url => $version) {
       if (are_equal($repository, $version->repository)) {
           return true;
       }
    }

    return false;
}
