<?php

namespace PhpRepos\Git\Signals;

use PhpRepos\Observer\Signals\Plan;

class SendingGitHttpRequest extends Plan
{
    public static function using(string $url, string $method, bool $has_token): static
    {
        return static::create('Sending HTTP request', [
            'url' => $url,
            'method' => $method,
            'has_token' => $has_token,
        ]);
    }
}
