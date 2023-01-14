<?php

namespace Tests\System\VersionCommandTest;

use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\Cli\IO\Write\assert_success;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should show version in the output',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg -v');

        assert_success('phpkg version 1.0.0', $output);
    }
);

test(
    title: 'it should show version in the output with version option',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg --version');

        assert_success('phpkg version 1.0.0', $output);
    }
);
