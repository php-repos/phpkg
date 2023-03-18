<?php

namespace Tests\System\InstallCommand\InstallWhenGithubTokenIsSetTest;

use PhpRepos\FileManager\JsonFile;
use function Phpkg\Providers\GitHub\github_token;
use function PhpRepos\FileManager\Directory\clean;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_empty_project;
use const Phpkg\Providers\GitHub\GITHUB_DOMAIN;

test(
    title: 'it should not show error message when the credential file is not exists and GITHUB_TOKEN is set',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg install --project=TestRequirements/Fixtures/EmptyProject');

        $packages = root() . 'TestRequirements/Fixtures/EmptyProject/Packages/';
        $expected = <<<EOD
\e[39mInstalling packages...
\e[39mSetting env credential...
\e[39mLoading configs...
\e[39mDownloading packages...
\e[39mDownloading package git@github.com:php-repos/released-package.git to {$packages}php-repos/released-package
\e[39mDownloading package git@github.com:php-repos/complex-package to {$packages}php-repos/complex-package
\e[39mDownloading package git@github.com:php-repos/simple-package.git to {$packages}php-repos/simple-package
\e[92mPackages has been installed successfully.\e[39m

EOD;

        assert_true($expected === $output, 'Output is not correct:' . PHP_EOL . $expected . PHP_EOL . $output);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/released-package.git --version=v1.0.1 --project=TestRequirements/Fixtures/EmptyProject');
        clean(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages'));
        $credential = JsonFile\to_array(root() . 'credentials.json');
        github_token($credential[GITHUB_DOMAIN]['token']);
        rename(root() . 'credentials.json', root() . 'credentials.json.back');
    },
    after: function () {
        reset_empty_project();
        rename(root() . 'credentials.json.back', root() . 'credentials.json');
    },
);
