<?php

namespace Phpkg\Git\Exception;

use InvalidArgumentException;

class NotSupportedVersionControlException extends InvalidArgumentException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
