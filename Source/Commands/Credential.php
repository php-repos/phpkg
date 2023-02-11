<?php

namespace Phpkg\Commands\Credential;

use Phpkg\Classes\Credential\Credential;
use Phpkg\Classes\Credential\Credentials;
use Phpkg\Classes\Environment\Environment;
use PhpRepos\FileManager\File;
use PhpRepos\FileManager\JsonFile;
use function PhpRepos\Cli\IO\Read\argument;
use function PhpRepos\Cli\IO\Write\success;

function run(Environment $environment): void
{
    $provider = argument(2);
    $token = argument(3);

    $credentials = File\exists($environment->credential_file)
        ? Credentials::from_array(JsonFile\to_array($environment->credential_file))
        : new Credentials();

    $credentials->push(new Credential($provider, $token));

    JsonFile\write($environment->credential_file, $credentials->to_array());

    success("Credential for $provider has been set successfully.");
}
