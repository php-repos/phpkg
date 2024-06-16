<?php

namespace Phpkg\Http\Request;

class Message
{
    public function __construct(
        public readonly Url $url,
        public readonly Method $method,
        public readonly Header $header,
    ) {}
}
