<?php

namespace Phpkg\Http\Response\Responses;

use Phpkg\Http\Response\Message;

function to_array(Message $response): array
{
    return json_decode($response->body, true);
}
