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
