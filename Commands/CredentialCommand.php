<?php

namespace Phpkg\Commands\Credential;

use Phpkg\BusinessSpecifications\Credential;
use PhpRepos\Console\Attributes\Argument;
use PhpRepos\Console\Attributes\Description;
use function PhpRepos\Cli\Output\error;
use function PhpRepos\Cli\Output\line;
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
    line("Adding credential for provider $provider...");

    $outcome = Credential\add($provider, $token);

    if (!$outcome->success) {
        error($outcome->message);
        return;
    }

    success($outcome->message);
};
