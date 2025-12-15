<?php

namespace Phpkg\Solution\Parser;

class SymbolRegistry
{
    public function __construct(
        public readonly array $classes,
        public readonly array $functions,
        public readonly array $constants,
        public readonly array $imports,
        public readonly array $namespaces,
    ) {}
}
