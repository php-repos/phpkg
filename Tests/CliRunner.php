<?php

namespace Tests\CliRunner;

use PhpRepos\Console\Business\Finder;
use PhpRepos\Console\Business\Command;

function phpkg(string $command, array $arguments = []): string
{
    $inputs = [$command, ...$arguments];

    $outcome = Finder\path(__DIR__.'/../Commands', 'Command.php');

    if (!$outcome->success) return $outcome->message;

    $command_handlers = $outcome->data['handlers'];

    $outcome = Command\find($command_handlers, $inputs);

    if (!$outcome->success) {
        return $outcome->message;
    }

    $command = $outcome->data['name'];
    $handler = $outcome->data['handler'];

    $outcome = Command\describe($handler);
    if (!$outcome->success) return $outcome->message;

    ob_start();
    $outcome = Command\run($command, $handler, $command_handlers, $inputs);
    if (!$outcome->success) return $outcome->message;

    return ob_get_clean();
}
