<?php

namespace Phpkg\Http\Request\Requests;

use Phpkg\Http\Request\Message;
use PhpRepos\Datatype\Pair;

function header_to_array(Message $request): array
{
    return $request->header->reduce(function (array $headers, Pair $header) {
        $headers[] = $header->key . ': ' . $header->value;

        return $headers;
    }, []);
}

function has_authorization(Message $request): bool
{
    return $request->header->has(fn (Pair $header) => $header->key === 'Authorization');
}