<?php

namespace PhpRepos\Git\Http\Request;

class Message
{
    public function __construct(
        public readonly Url $url,
        public readonly string $method,
        public readonly Header $header,
    ) {}
}
