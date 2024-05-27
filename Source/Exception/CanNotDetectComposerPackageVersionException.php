<?php

namespace Phpkg\Exception;

use Exception;

class CanNotDetectComposerPackageVersionException extends Exception
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
