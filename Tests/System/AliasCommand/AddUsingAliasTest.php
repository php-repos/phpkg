<?php

namespace Tests\System\AliasCommand\AddUsingAliasTest;

use function PhpRepos\FileManager\Directory\clean;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should add package using alias',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg add test-runner --project=TestRequirements/Fixtures/EmptyProject');

        $expected = <<<EOD
\e[39mAdding package test-runner latest version...
\e[39mSetting env credential...
\e[39mLoading configs...
\e[39mChecking installed packages...
\e[39mSetting package version...
\e[39mCreating package directory...
\e[39mDetecting version hash...
\e[39mDownloading the package...
\e[39mUpdating configs...
\e[39mCommitting configs...
\e[92mPackage git@github.com:php-repos/test-runner.git has been added successfully.\e[39m

EOD;

        assert_true($expected === $output, 'Output is not correct:' . PHP_EOL . $expected . PHP_EOL . $output);
        assert_true(file_exists(root() .  'TestRequirements/Fixtures/EmptyProject/Packages/php-repos/test-runner'));
        assert_true(file_exists(root() .  'TestRequirements/Fixtures/EmptyProject/Packages/php-repos/test-runner/phpkg.config.json'));
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg alias test-runner git@github.com:php-repos/test-runner.git --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        clean(realpath(root() . 'TestRequirements/Fixtures/EmptyProject'));
    }
);
