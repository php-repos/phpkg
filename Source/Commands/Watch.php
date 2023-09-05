<?php

use PhpRepos\Console\Attributes\Description;
use PhpRepos\Console\Attributes\LongOption;

/**
 * Enables you to monitor file changes in your project and automatically build the project for each change.
 * When you run this command, `phpkg` continuously builds your project files while you are actively developing. It is
 * designed to operate exclusively in the `development` environment.
 */
return function (
    #[LongOption('project')]
    #[Description('When working in a different directory, provide the relative project path for correct package placement.')]
    ?string $project = '',
    #[LongOption('wait')]
    #[Description('Specify the interval in seconds at which the watch command runs the build process.')]
    ?int $wait = 3,
): void
{
    global $argv;

    $seconds = $wait;
    $command = "php $argv[0] build";
    $command = $project ? $command . ' --project=' . $project : $command;

    while (true) {
        echo shell_exec($command);

        sleep($seconds);
    }
};
