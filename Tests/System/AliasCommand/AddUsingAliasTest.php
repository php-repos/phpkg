<?php

namespace Tests\System\AliasCommand\AddUsingAliasTest;

use function PhpRepos\Cli\Output\assert_line;
use function PhpRepos\Cli\Output\assert_success;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_dummy_project;

test(
    title: 'it should add package using alias',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg add test-runner --project=../../DummyProject');

        $lines = explode("\n", trim($output));

        assert_true(7 === count($lines), 'Number of output lines do not match' . $output);
        assert_line("Adding package test-runner latest version...", $lines[0] . PHP_EOL);
        assert_line("Checking installed packages...", $lines[1] . PHP_EOL);
        assert_line("Setting package version...", $lines[2] . PHP_EOL);
        assert_line("Adding the package...", $lines[3] . PHP_EOL);
        assert_line("Updating configs...", $lines[4] . PHP_EOL);
        assert_line("Committing configs...", $lines[5] . PHP_EOL);
        assert_success("Package git@github.com:php-repos/test-runner.git has been added successfully.", $lines[6] . PHP_EOL);

        assert_true(file_exists(root() . '../../DummyProject/Packages/php-repos/test-runner'));
        assert_true(file_exists(root() . '../../DummyProject/Packages/php-repos/test-runner/phpkg.config.json'));
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg alias test-runner git@github.com:php-repos/test-runner.git --project=../../DummyProject');
    },
    after: function () {
        reset_dummy_project();
    }
);
