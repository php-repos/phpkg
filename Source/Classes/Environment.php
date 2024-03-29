<?php

namespace Phpkg\Classes;

use PhpRepos\FileManager\Path;

class Environment
{
    public function __construct(
        public readonly Path $pwd,
        public readonly Path $credential_file,
        public readonly Path $temp,
    ) {}

    public static function setup(): static
    {
        return new static(
            Path::from_string($_SERVER['PWD']),
            Path::from_string($_SERVER['SCRIPT_FILENAME'])->sibling('credentials.json'),
            Path::from_string(sys_get_temp_dir())->append('phpkg'),
        );
    }
}
