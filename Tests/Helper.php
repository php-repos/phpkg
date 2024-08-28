<?php

namespace Tests\Helper;

use PhpRepos\FileManager\Path;
use function chmod;
use function Phpkg\System\is_windows;
use function PhpRepos\FileManager\Directory\clean;
use function PhpRepos\FileManager\Directory\delete_recursive;
use function PhpRepos\FileManager\Directory\ls_recursively;
use function PhpRepos\FileManager\File\create;
use function PhpRepos\FileManager\Resolver\root;

function reset_dummy_project(): void
{
    $path = Path::from_string(root() . '../../DummyProject');

    if (is_windows()) {
        ls_recursively($path)
            ->vertices()
            ->each(fn ($filename) => chmod($filename, 0777));
    }

    clean($path);
    create($path->append('.gitignore'), '*' . PHP_EOL);
}

function force_delete(string $path): bool
{
    if (is_windows()) {
        ls_recursively($path)->vertices()->each(fn ($filename) => chmod($filename, 0777));
    }

    return delete_recursive($path);
}

function CRLF_to_EOL(string $str): string
{
    return str_replace("\r\n", "\n", $str);
}
