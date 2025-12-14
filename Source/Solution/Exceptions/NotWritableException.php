<?php

namespace Phpkg\Solution\Exceptions;

use Exception;

class NotWritableException extends Exception
{
    public function __construct(string $path, ?Exception $previous = null)
    {
        parent::__construct("Path '{$path}' is not writable", 0, $previous);
    }
}
