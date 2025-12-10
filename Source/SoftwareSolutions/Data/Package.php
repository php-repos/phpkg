<?php

namespace Phpkg\SoftwareSolutions\Data;

class Package
{
    public string $root;
    public string $checksum;

    public function __construct(
        public readonly Commit $commit,
        public readonly array $config,
    ) {}

    public function root(string $root): static
    {
        $this->root = $root;
        return $this;
    }

    public function checksum(string $checksum): static
    {
        $this->checksum = $checksum;
        return $this;
    }

    public function identifier(): string
    {
        return $this->commit->identifier();
    }
}

