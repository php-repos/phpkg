<?php

namespace Tests\CliRunner;

use PhpRepos\Console\Input;
use PhpRepos\FileManager\Path;
use function Phpkg\Cli\Runner\execute;
use function PhpRepos\Console\Runner\from_path;

function phpkg(string $command, array $arguments = []): string
{
    ob_start();
    $inputs = Input::make([$command, ...$arguments]);
    $commands_directory = Path::from_string(__DIR__ . '/../Commands');
    execute(from_path($commands_directory), $inputs, false, $commands_directory, '[-v | -vv | -vvv]');

    $output = ob_get_contents();
    ob_end_clean();

    return $output;
}
