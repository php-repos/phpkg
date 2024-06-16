<?php

namespace Tests\System\BuildCommand\BuildWithInvalidInputTest;

use function PhpRepos\Cli\Output\assert_error;
use function PhpRepos\Cli\Output\assert_line;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should return error message when the build mode is invalid',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg build invalid --project=TestRequirements/Fixtures/EmptyProject');

        $lines = explode("\n", trim($output));

        assert_error('Error: Build mode env should be either `development` or `production`', $lines[0] . PHP_EOL);
        assert_line('Usage: phpkg build [<options>] [<env>]', $lines[1] . PHP_EOL);
    }
);
