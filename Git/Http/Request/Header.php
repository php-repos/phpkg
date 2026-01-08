<?php

namespace PhpRepos\Git\Http\Request;

class Header
{
    private array $headers = [];

    public function put(string $key, string $value): Header
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function to_array(): array
    {
        return $this->headers;
    }
}
