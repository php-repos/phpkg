<?php

namespace Tests\System\UpdateCommand\UpdatePackagesWithSharedDependenciesTest;

use function PhpRepos\Cli\Output\assert_line;
use function PhpRepos\Cli\Output\assert_success;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_dummy_project;

test(
    title: 'it should update packages with shared dependencies',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg update git@github.com:php-repos/file-manager.git --force --project=../../DummyProject');

        $lines = explode("\n", trim($output));

        assert_true(7 === count($lines), 'Number of output lines do not match' . $output);
        assert_line("Updating package git@github.com:php-repos/file-manager.git to latest version...", $lines[0] . PHP_EOL);
        assert_line("Finding package in configs...", $lines[1] . PHP_EOL);
        assert_line("Setting package version...", $lines[2] . PHP_EOL);
        assert_line("Updating package...", $lines[3] . PHP_EOL);
        assert_line("Updating configs...", $lines[4] . PHP_EOL);
        assert_line("Committing new configs...", $lines[5] . PHP_EOL);
        assert_success("Package git@github.com:php-repos/file-manager.git has been updated.", $lines[6] . PHP_EOL);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/test-runner.git --version=v1 --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/datatype.git --version=v1 --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/file-manager.git --version=v2 --project=../../DummyProject');
    },
    after: function () {
        reset_dummy_project();
    }
);
