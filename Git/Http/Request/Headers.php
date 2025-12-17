<?php

namespace PhpRepos\Git\Http\Request\Headers;

use PhpRepos\Git\Http\Request\Header;

function accept(Header $header, string $accept): Header
{
    return $header->put('Accept', $accept);
}

function authorization(Header $header, string $authorization): Header
{
    return $header->put('Authorization', $authorization);
}

function user_agent(Header $header, string $user_agent): Header
{
    return $header->put('User-Agent', $user_agent);
}
