<?php

namespace Phpkg\Classes;

use PhpRepos\Datatype\Collection;
use function PhpRepos\Datatype\Arr\reduce;

class Credentials extends Collection
{
    public static function from_array(array $credentials): static
    {
        return reduce($credentials, function (Credentials $carry, array $provider_credentials, string $provider) {
            return $carry->push(new Credential($provider, $provider_credentials['token']));
        }, new static());
    }
}
