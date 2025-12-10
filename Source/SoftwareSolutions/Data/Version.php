<?php

namespace Phpkg\SoftwareSolutions\Data;

class Version
{
    public function __construct(
        public readonly Repository $repository,
        public readonly string $tag,
    ) {}

    public function identifier(): string
    {
        return sprintf('%s@%s', $this->repository->identifier(), $this->tag);
    }
}
