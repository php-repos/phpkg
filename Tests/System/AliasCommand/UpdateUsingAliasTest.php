<?php

namespace Tests\System\AliasCommand\UpdateUsingAliasTest;

use function PhpRepos\FileManager\Directory\clean;
use function PhpRepos\FileManager\JsonFile\to_array;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_false;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should update package using alias',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg update released-package --project=TestRequirements/Fixtures/EmptyProject');
        $expected = <<<EOD
\e[39mUpdating package released-package to latest version...
\e[39mSetting env credential...
\e[39mLoading configs...
\e[39mFinding package in configs...
\e[39mSetting package version...
\e[39mLoading package's config...
\e[39mDeleting package's files...
\e[39mDetecting version hash...
\e[39mDownloading the package with new version...
\e[39mUpdating configs...
\e[39mCommitting new configs...
\e[92mPackage git@github.com:php-repos/released-package.git has been updated.\e[39m

EOD;

        assert_true($expected === $output, 'Output is not correct:' . PHP_EOL . $expected . PHP_EOL . $output);
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
        clean(realpath(root() . 'TestRequirements/Fixtures/EmptyProject'));
    }
);
