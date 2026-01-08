<?php

namespace PhpRepos\Git\Http\Request;

class Message
{
    public function __construct(
        public readonly string $url,
        public readonly string $method,
        public readonly Header $header,
    ) {}
}
