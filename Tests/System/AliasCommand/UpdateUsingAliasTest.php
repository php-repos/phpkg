<?php

namespace Tests\System\AliasCommand\UpdateUsingAliasTest;

use function PhpRepos\Cli\Output\assert_line;
use function PhpRepos\Cli\Output\assert_success;
use function PhpRepos\FileManager\JsonFile\to_array;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_false;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_empty_project;

test(
    title: 'it should update package using alias',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg update released-package --project=TestRequirements/Fixtures/EmptyProject');

        $lines = explode("\n", trim($output));

        assert_true(8 === count($lines), 'Number of output lines do not match' . $output);
        assert_line("Updating package released-package to latest version...", $lines[0] . PHP_EOL);
        assert_line("Setting env credential...", $lines[1] . PHP_EOL);
        assert_line("Finding package in configs...", $lines[2] . PHP_EOL);
        assert_line("Setting package version...", $lines[3] . PHP_EOL);
        assert_line("Updating package...", $lines[4] . PHP_EOL);
        assert_line("Updating configs...", $lines[5] . PHP_EOL);
        assert_line("Committing new configs...", $lines[6] . PHP_EOL);
        assert_success("Package git@github.com:php-repos/released-package.git has been updated.", $lines[7] . PHP_EOL);

        assert_true(file_exists(root() .  'TestRequirements/Fixtures/EmptyProject/Packages/php-repos/released-package'));
        $config_file = root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json';
        assert_false(
            'v1.0.0'
            ===
            to_array($config_file)['packages']['git@github.com:php-repos/released-package.git']
        );
        $meta_file = root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config-lock.json';
        assert_false(
            'be24f45d8785c215901ba90b242f3b8a7d2bdbfb'
            ===
            to_array($meta_file)['packages']['git@github.com:php-repos/released-package.git']
        );
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg alias released-package git@github.com:php-repos/released-package.git --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg add released-package v1.0.0 --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        reset_empty_project();
    }
);
