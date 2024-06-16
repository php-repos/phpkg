<?php

namespace Phpkg\Http\Request\Headers;

use Phpkg\Http\Request\Header;
use PhpRepos\Datatype\Pair;

function accept(Header $header, string $accept): Header
{
    return $header->push(new Pair('Accept', $accept));
}

function authorization(Header $header, string $authorization): Header
{
    return $header->push(new Pair('Authorization', $authorization));
}

function user_agent(Header $header, string $user_agent): Header
{
    return $header->push(new Pair('User-Agent', $user_agent));
}
