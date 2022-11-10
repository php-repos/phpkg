<?php

namespace Saeghe\Saeghe\Exception;

use Saeghe\Saeghe\Git\Exception\InvalidTokenException;
use function Saeghe\Cli\IO\Write\error;

function register_exception_handler()
{
    set_exception_handler(function (\Throwable $exception) {
        if ($exception instanceof InvalidTokenException) {
            error('The GitHub token is not valid. Either, you didn\'t set one yet, or it is not valid. Please use the `credential` command to set a valid token.');
        }

        if ($exception instanceof CredentialCanNotBeSetException) {
            error($exception->getMessage());
        }
    });
}
