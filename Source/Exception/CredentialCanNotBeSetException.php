<?php

namespace Saeghe\Saeghe\Exception;

class CredentialCanNotBeSetException extends \Exception
{
    public function __construct(string $message = "")
    {
        parent::__construct($message);
    }
}
