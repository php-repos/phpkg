<?php

namespace PhpRepos\Git\Http\Response\Responses;

use PhpRepos\Git\Http\Response\Message;

function to_array(Message $response): array
{
    return json_decode($response->body, true);
}
