<?php

namespace Tests\Helper;

use PhpRepos\FileManager\Path;
use function PhpRepos\FileManager\Directory\clean;
use function PhpRepos\FileManager\File\create;
use function PhpRepos\FileManager\Resolver\root;

function reset_empty_project()
{
    $path = Path::from_string(root() . 'TestRequirements/Fixtures/EmptyProject');
    clean($path);
    create($path->append('.gitignore'), '*' . PHP_EOL);
}

function replace_newlines_with_phpeol(string $str): string
{
    return str_replace("\n", PHP_EOL, $str);
}

function add_info(string $message): string
{
    return "\033[39m$message" . PHP_EOL;
}

function add_error(string $message): string
{
    return "\033[91m$message\033[39m" . PHP_EOL;
}

function add_success(string $message): string
{
    return "\033[92m$message\033[39m" . PHP_EOL;
}
