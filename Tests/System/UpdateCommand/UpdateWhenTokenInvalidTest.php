<?php

namespace Tests\System\UpdateCommand\UpdateWhenTokenInvalidTest;

use function Phpkg\Providers\GitHub\github_token;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_empty_project;

test(
    title: 'it should show error message when token is invalid',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg update git@github.com:php-repos/released-package.git --project=TestRequirements/Fixtures/EmptyProject');
        $expected = <<<EOD
\e[39mUpdating package git@github.com:php-repos/released-package.git to latest version...
\e[39mSetting env credential...
\e[39mLoading configs...
\e[39mFinding package in configs...
\e[39mSetting package version...
\e[91mThe GitHub token is not valid. Either, you didn't set one yet, or it is not valid. Please use the `credential` command to set a valid token.\e[39m

EOD;

        assert_true($expected === $output, 'Output is not correct:' . PHP_EOL . $expected . PHP_EOL . $output);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/released-package.git --version=v1.0.1 --project=TestRequirements/Fixtures/EmptyProject');
        rename(root() . 'credentials.json', root() . 'credentials.json.back');
        shell_exec('php ' . root() . 'phpkg credential github.com not-valid');
        github_token('');
    },
    after: function () {
        reset_empty_project();
        rename(root() . 'credentials.json.back', root() . 'credentials.json');
    }
);
