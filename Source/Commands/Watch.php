<?php

namespace Phpkg\Commands\Watch;

use function PhpRepos\Cli\IO\Read\parameter;

return function (): void
{
    global $argv;

    $seconds = (int) parameter('wait', 3);
    $project = parameter('project');
    $command = "php $argv[0] build";
    $command = $project ? $command . ' --project=' . $project : $command;

    while (true) {
        echo shell_exec($command);

        sleep($seconds);
    }
};
