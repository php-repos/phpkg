<?php

namespace Phpkg\Http;

class Conversation
{
    public function __construct(
        public readonly Request\Message $request,
        public readonly Response\Message $response,
    ) {}
}
