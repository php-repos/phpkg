<?php

namespace Tests\System\AliasCommand\RemoveUsingAliasTest;

use function PhpRepos\FileManager\Directory\clean;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_false;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should remove package using alias',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg remove released-package --project=TestRequirements/Fixtures/EmptyProject');
        $expected = <<<EOD
\e[39mRemoving package released-package
\e[39mLoading configs...
\e[39mFinding package in configs...
\e[39mLoading package's config...
\e[39mRemoving package from config...
\e[39mCommitting configs...
\e[92mPackage git@github.com:php-repos/released-package.git has been removed successfully.\e[39m

EOD;

        assert_true($expected === $output, 'Output is not correct:' . PHP_EOL . $expected . PHP_EOL . $output);
        assert_false(file_exists(root() .  'TestRequirements/Fixtures/EmptyProject/Packages/php-repos/released-package'));
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg alias released-package git@github.com:php-repos/released-package.git --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg add released-package --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        clean(realpath(root() . 'TestRequirements/Fixtures/EmptyProject'));
    }
);
