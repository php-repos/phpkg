<?php

namespace Tests\System\RemoveCommand\RemovePackageWithMultipleDependenciesTest;

use function PhpRepos\Cli\Output\assert_line;
use function PhpRepos\Cli\Output\assert_success;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_false;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_empty_project;

test(
    title: 'it should remove package with multiple dependencies',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg remove git@github.com:php-repos/released-package.git --project=TestRequirements/Fixtures/EmptyProject');

        $lines = explode("\n", trim($output));

        assert_true(5 === count($lines), 'Number of output lines do not match' . $output);
        assert_line("Removing package git@github.com:php-repos/released-package.git", $lines[0] . PHP_EOL);
        assert_line("Finding package in configs...", $lines[1] . PHP_EOL);
        assert_line("Removing package from config...", $lines[2] . PHP_EOL);
        assert_line("Committing configs...", $lines[3] . PHP_EOL);
        assert_success("Package git@github.com:php-repos/released-package.git has been removed successfully.", $lines[4] . PHP_EOL);

        assert_false(file_exists(root() .  'TestRequirements/Fixtures/EmptyProject/Packages/php-repos/released-package'));
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/released-package.git --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        reset_empty_project();
    }
);
