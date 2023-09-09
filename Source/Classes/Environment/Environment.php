<?php

namespace Phpkg\Classes\Environment;

use PhpRepos\FileManager\Path;

class Environment
{
    public function __construct(
        public readonly Path $pwd,
        public readonly Path $credential_file,
    ) {}

    public static function for_project(): static
    {
        return new static(
            Path::from_string($_SERVER['PWD']),
            Path::from_string($_SERVER['SCRIPT_FILENAME'])->sibling('credentials.json')
        );
    }
}
