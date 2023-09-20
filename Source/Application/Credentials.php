<?php

namespace Phpkg\Application\Credentials;

use Phpkg\Classes\Credential;
use Phpkg\Classes\Credentials;
use Phpkg\Classes\Environment;
use Phpkg\Exception\CredentialCanNotBeSetException;
use PhpRepos\FileManager\File;
use PhpRepos\FileManager\JsonFile;
use function Phpkg\Providers\GitHub\github_token;
use const Phpkg\Providers\GitHub\GITHUB_DOMAIN;

function set_credentials(Environment $environment): void
{
    $environment_token = github_token();

    if (strlen($environment_token) > 0) {
        return;
    }

    if (! File\exists($environment->credential_file)) {
        throw new CredentialCanNotBeSetException('There is no credential file. Please use the `credential` command to add your token.');
    }

    /** @var Credential $github_credential */
    $github_credential = Credentials::from_array(JsonFile\to_array($environment->credential_file))
        ->first(fn (Credential $credential) => $credential->key === GITHUB_DOMAIN);

    github_token(is_null($github_credential) ? '' : $github_credential->value);
}
