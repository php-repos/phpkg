<?php

namespace Phpkg\BusinessSpecifications;

class Outcome
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $message = '',
        public ?array $data = [],
    ) {}
}
