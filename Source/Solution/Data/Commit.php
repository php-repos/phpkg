<?php

namespace Phpkg\Solution\Data;

class Commit
{
    public function __construct(
        public readonly Version $version,
        public readonly string $hash,
    ) {}

    public function identifier(): string
    {
        return sprintf('%s#%s', $this->version->identifier(), $this->hash);
    }
}
