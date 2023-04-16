<?php

namespace Tests\System\InstallCommand\InstallWhenGithubTokenIsSetTest;

use PhpRepos\FileManager\JsonFile;
use function Phpkg\Providers\GitHub\github_token;
use function PhpRepos\Cli\IO\Write\assert_line;
use function PhpRepos\Cli\IO\Write\assert_success;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\force_delete;
use function Tests\Helper\reset_empty_project;
use const Phpkg\Providers\GitHub\GITHUB_DOMAIN;

test(
    title: 'it should not show error message when the credential file is not exists and GITHUB_TOKEN is set',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg install --project=TestRequirements/Fixtures/EmptyProject');

        $lines = explode("\n", trim($output));

        assert_true(5 === count($lines), 'Number of output lines do not match' . $output);
        assert_line("Installing packages...", $lines[0] . PHP_EOL);
        assert_line("Setting env credential...", $lines[1] . PHP_EOL);
        assert_line("Loading configs...", $lines[2] . PHP_EOL);
        assert_line("Downloading packages...", $lines[3] . PHP_EOL);
        assert_success("Packages has been installed successfully.", $lines[4] . PHP_EOL);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/released-package.git --version=v1.0.1 --project=TestRequirements/Fixtures/EmptyProject');
        force_delete(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages'));
        $credential = JsonFile\to_array(root() . 'credentials.json');
        github_token($credential[GITHUB_DOMAIN]['token']);
        rename(root() . 'credentials.json', root() . 'credentials.json.back');
    },
    after: function () {
        reset_empty_project();
        rename(root() . 'credentials.json.back', root() . 'credentials.json');
    },
);
