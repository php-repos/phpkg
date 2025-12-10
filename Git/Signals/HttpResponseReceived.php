<?php

namespace PhpRepos\Git\Signals;

use PhpRepos\Observer\Signals\Event;

class HttpResponseReceived extends Event
{
    public static function with(string $url, string $method, int $status_code, float $duration): static
    {
        return static::create('HTTP response received', [
            'url' => $url,
            'method' => $method,
            'status_code' => $status_code,
            'duration' => $duration,
        ]);
    }
}

