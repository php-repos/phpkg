<?php

namespace Phpkg\Environments;

use Phpkg\Classes\Credential;
use Phpkg\Classes\Credentials;
use Phpkg\Classes\Environment;
use PhpRepos\FileManager\JsonFile;
use const Phpkg\Git\GitHub\GITHUB_DOMAIN;

function has_github_token(Environment $environment): bool
{
    return Credentials::from_array(JsonFile\to_array($environment->credential_file))
        ->has(fn (Credential $credential) => $credential->key === GITHUB_DOMAIN && strlen($credential->value) > 0);
}

function github_token(Environment $environment): string
{
    return Credentials::from_array(JsonFile\to_array($environment->credential_file))
        ->first(fn (Credential $credential) => $credential->key === GITHUB_DOMAIN)->value;
}
