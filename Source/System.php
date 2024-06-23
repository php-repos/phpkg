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
    return new Environment(
        pwd: $pwd = Path::from_string($_SERVER['PWD']),
        credential_file: str_starts_with($_SERVER['SCRIPT_FILENAME'], '..')
            ? $pwd->append($_SERVER['SCRIPT_FILENAME'])->sibling('credentials.json')
            : Path::from_string($_SERVER['SCRIPT_FILENAME'])->sibling('credentials.json'),
        temp: Path::from_string(sys_get_temp_dir())->append('phpkg'),
        github_token: strlen(getenv('GITHUB_TOKEN')) > 0 ? getenv('GITHUB_TOKEN') : null,
    );
}
