<?php

namespace Phpkg\Cli\Runner;

use PhpRepos\Console\CommandHandlers;
use PhpRepos\Console\Input;
use PhpRepos\FileManager\Path;
use function PhpRepos\Console\Runner\run;

function execute(CommandHandlers $command_handlers, Input $inputs, bool $wants_help, string $commands_directory, string $additional_handled_flags): int
{
    $entrypoint = 'phpkg';

    $help_text = <<<EOD
Usage: $entrypoint [-h | --help]$additional_handled_flags
               <command> [<options>] [<args>]
EOD;

    return run($command_handlers, $inputs, $entrypoint, $help_text, $wants_help, Path::from_string($commands_directory));
}
