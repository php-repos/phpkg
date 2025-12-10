<?php

namespace Phpkg\SoftwareSolutions\Data;

class Repository
{
    public function __construct(
        public readonly string $url,
        public readonly string $domain,
        public readonly string $owner,
        public readonly string $repo,
        public readonly ?string $token = null,
    ) {}

    public function identifier(): string
    {
        return sprintf('%s:%s/%s', $this->domain, $this->owner, $this->repo);
    }
}