<?php

namespace Phpkg\Commands\Credential;

use Phpkg\Classes\Credential;
use Phpkg\Classes\Credentials;
use Phpkg\System;
use PhpRepos\Console\Attributes\Argument;
use PhpRepos\Console\Attributes\Description;
use PhpRepos\FileManager\File;
use PhpRepos\FileManager\JsonFile;
use function PhpRepos\Cli\Output\success;

/**
 * The `credential` command is used to add a security token for a specified Git provider to the credential file.
 * This security token is essential for accessing package metadata from the desired Git repository securely.
 */
return function (
    #[Argument]
    #[Description("The domain name of the Git provider for which you want to add a security credential.\n For example github.com")]
    string $provider,
    #[Argument]
    #[Description('The security token that will be used to authenticate your access to the provider\'s services.')]
    string $token,
) {
    $environment = System\environment();

    $credentials = File\exists($environment->credential_file)
        ? Credentials::from_array(JsonFile\to_array($environment->credential_file))
        : new Credentials();

    $credentials->push(new Credential($provider, $token));

    JsonFile\write($environment->credential_file,
        $credentials->reduce(function (array $carry, Credential $credential) {
            $carry[$credential->key] = ['token' => $credential->value];

            return $carry;
        }, [])
    );

    success("Credential for $provider has been set successfully.");
};
