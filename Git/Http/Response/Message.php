<?php

namespace PhpRepos\Git\Http\Response;

class Message
{
    public function __construct(
        public readonly Status $status,
        public readonly Header $header,
        public readonly Body $body,
    ) {}
}