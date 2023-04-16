<?php

namespace Tests\System\BuildCommand\BuildHelper;

use function PhpRepos\FileManager\Resolver\realpath;

function replace_build_vars(string $build_path, string $file_path): string
{
    $content = str_replace('$environment_build_path', realpath($build_path), file_get_contents($file_path));
    return str_replace('$DS', DIRECTORY_SEPARATOR, $content);
}
