<?php

namespace Phpkg\Environments;

use Phpkg\Classes\Credential;
use Phpkg\Classes\Credentials;
use Phpkg\Classes\Environment;
use PhpRepos\FileManager\JsonFile;
use const Phpkg\Git\GitHub\GITHUB_DOMAIN;

function has_github_token_env(Environment $environment): bool
{
    return $environment->github_token !== null;
}

function has_defined_github_credential(Environment $environment): bool
{
    return Credentials::from_array(JsonFile\to_array($environment->credential_file))
        ->has(fn (Credential $credential) => $credential->key === GITHUB_DOMAIN && strlen($credential->value) > 0);
}

function has_github_token(Environment $environment): bool
{
    return has_defined_github_credential($environment) || has_github_token_env($environment);
}

function github_token(Environment $environment): string
{
    return has_defined_github_credential($environment)
        ? Credentials::from_array(JsonFile\to_array($environment->credential_file))
            ->first(fn (Credential $credential) => $credential->key === GITHUB_DOMAIN)->value
        : $environment->github_token;
}
