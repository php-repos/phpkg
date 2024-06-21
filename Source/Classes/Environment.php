<?php

namespace Phpkg\Classes;

use PhpRepos\FileManager\Path;

class Environment
{
    public function __construct(
        public readonly Path $pwd,
        public readonly Path $credential_file,
        public readonly Path $temp,
        public readonly ?string $github_token,
    ) {}
}
