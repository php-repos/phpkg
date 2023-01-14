<?php

namespace Phpkg\Commands\Credential;

use Phpkg\Classes\Credential\Credential;
use Phpkg\Classes\Credential\Credentials;
use Phpkg\Classes\Environment\Environment;
use PhpRepos\FileManager\FileType\Json;
use function PhpRepos\Cli\IO\Read\argument;
use function PhpRepos\Cli\IO\Write\success;

function run(Environment $environment): void
{
    $provider = argument(2);
    $token = argument(3);

    $credentials = $environment->credential_file->path->exists()
        ? Credentials::from_array(Json\to_array($environment->credential_file->path))
        : new Credentials();

    $credentials->push(new Credential($provider, $token));

    Json\write($environment->credential_file->path, $credentials->to_array());

    success("Credential for $provider has been set successfully.");
}
