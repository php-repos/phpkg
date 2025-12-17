<?php

namespace PhpRepos\Git\Http;

class Conversation
{
    public function __construct(
        public readonly Request\Message $request,
        public readonly Response\Message $response,
    ) {}
}
