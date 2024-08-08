<?php

namespace Tests\System\VersionCommandTest;

use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\Cli\Output\assert_success;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should show version in the output',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg -v');

        $lines = explode("\n", trim($output));
        assert_true(1 === count($lines), 'Number of output lines do not match' . $output);
        assert_success('phpkg version 1.9.0', $lines[0] . PHP_EOL);
    }
);

test(
    title: 'it should show version in the output with version option',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg --version');

        $lines = explode("\n", trim($output));
        assert_true(1 === count($lines), 'Number of output lines do not match' . $output);
        assert_success('phpkg version 1.9.0', $lines[0] . PHP_EOL);
    }
);
