<?php

namespace Phpkg\Exception;

use Phpkg\Git\Exception\InvalidTokenException;
use Throwable;
use function PhpRepos\Cli\Output\error;
use function PhpRepos\Cli\Output\line;

function register_exception_handler(): void
{
    set_exception_handler(function (Throwable $exception) {
        if ($exception instanceof InvalidTokenException) {
            error('The GitHub token is not valid. Either, you didn\'t set one yet, or it is not valid. Please use the `credential` command to set a valid token.');
        } else if ($exception instanceof CredentialCanNotBeSetException || $exception instanceof PreRequirementsFailedException) {
            error($exception->getMessage());
        } else {
            error($exception->getMessage());
            line($exception);
        }
    });
}
