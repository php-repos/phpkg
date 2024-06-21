<?php

namespace Phpkg\System;

use Phpkg\Classes\Environment;
use PhpRepos\FileManager\Path;

function is_windows(): bool
{
    return PHP_OS === 'WINNT';
}

function random_temp_directory(): string
{
    return sys_get_temp_dir() . '/' . uniqid();
}

function environment(): Environment
{
    $github_token_var = getenv('GITHUB_TOKEN');

    return new Environment(
        Path::from_string($_SERVER['PWD']),
        Path::from_string($_SERVER['SCRIPT_FILENAME'])->sibling('credentials.json'),
        Path::from_string(sys_get_temp_dir())->append('phpkg'),
        strlen($github_token_var) > 0 ? $github_token_var : null,
    );
}
