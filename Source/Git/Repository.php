<?php

namespace Phpkg\Git;

use function Phpkg\Providers\GitHub\extract_owner;
use function Phpkg\Providers\GitHub\extract_repo;

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

    public static function from_meta(array $meta): static
    {
        return (new static('github.com', $meta['owner'], $meta['repo']))
            ->version($meta['version'])
            ->hash($meta['hash']);
    }

    public function version(string $version): static
    {
        $this->version = $version;

        return $this;
    }

    public function hash(string $hash): static
    {
        $this->hash = $hash;

        return $this;
    }

    public function is(self $repository): bool
    {
        return $repository->owner === $this->owner && $repository->repo === $this->repo;
    }
}
