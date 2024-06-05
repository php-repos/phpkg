<?php

namespace Phpkg\Git;

use function Phpkg\Git\GitHub\extract_owner;
use function Phpkg\Git\GitHub\extract_repo;

class Repository
{
    public string $version;
    public string $hash;

    public function __construct(
        public readonly string $domain,
        public readonly string $owner,
        public readonly string $repo,
    ) {}

    public static function from_url(string $package_url): static
    {
        $owner = extract_owner($package_url);
        $repo = extract_repo($package_url);

        return new static('github.com', $owner, $repo);
    }

    public function is_github(): bool
    {
        return $this->domain === 'github.com';
    }
}
