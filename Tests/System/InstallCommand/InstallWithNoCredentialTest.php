<?php

namespace Tests\System\InstallCommand\InstallWithNoCredentialTest;

use function Phpkg\Providers\GitHub\github_token;
use function PhpRepos\Cli\IO\Write\assert_error;
use function PhpRepos\Cli\IO\Write\assert_line;
use function PhpRepos\FileManager\Directory\clean;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_empty_project;

test(
    title: 'it should show error message when the credential file is not exists and there is no GITHUB_TOKEN',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg install --project=TestRequirements/Fixtures/EmptyProject');

        $lines = explode("\n", trim($output));

        assert_true(3 === count($lines), 'Number of output lines do not match' . $output);
        assert_line("Installing packages...", $lines[0] . PHP_EOL);
        assert_line("Setting env credential...", $lines[1] . PHP_EOL);
        assert_error("There is no credential file. Please use the `credential` command to add your token.", $lines[2] . PHP_EOL);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/released-package.git --version=v1.0.1 --project=TestRequirements/Fixtures/EmptyProject');
        clean(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages'));
        rename(root() . 'credentials.json', root() . 'credentials.json.back');
        github_token('');
    },
    after: function () {
        reset_empty_project();
        rename(root() . 'credentials.json.back', root() . 'credentials.json');
    },
);
