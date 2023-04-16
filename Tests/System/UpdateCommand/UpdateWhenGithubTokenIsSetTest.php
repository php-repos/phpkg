<?php

namespace Tests\System\UpdateCommand\UpdateWhenGithubTokenIsSetTest;

use PhpRepos\FileManager\JsonFile;
use function Phpkg\Providers\GitHub\github_token;
use function PhpRepos\Cli\IO\Write\assert_line;
use function PhpRepos\Cli\IO\Write\assert_success;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_empty_project;
use const Phpkg\Providers\GitHub\GITHUB_DOMAIN;


test(
    title: 'it should not show error message if the credential file is not exist and GITHUB_TOKEN is set',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg update git@github.com:php-repos/released-package.git --project=TestRequirements/Fixtures/EmptyProject');

        $lines = explode("\n", trim($output));

        assert_true(12 === count($lines), 'Number of output lines do not match' . $output);
        assert_line("Updating package git@github.com:php-repos/released-package.git to latest version...", $lines[0] . PHP_EOL);
        assert_line("Setting env credential...", $lines[1] . PHP_EOL);
        assert_line("Loading configs...", $lines[2] . PHP_EOL);
        assert_line("Finding package in configs...", $lines[3] . PHP_EOL);
        assert_line("Setting package version...", $lines[4] . PHP_EOL);
        assert_line("Loading package's config...", $lines[5] . PHP_EOL);
        assert_line("Deleting package's files...", $lines[6] . PHP_EOL);
        assert_line("Detecting version hash...", $lines[7] . PHP_EOL);
        assert_line("Downloading the package with new version...", $lines[8] . PHP_EOL);
        assert_line("Updating configs...", $lines[9] . PHP_EOL);
        assert_line("Committing new configs...", $lines[10] . PHP_EOL);
        assert_success("Package git@github.com:php-repos/released-package.git has been updated.", $lines[11] . PHP_EOL);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/released-package.git --version=v1.0.1 --project=TestRequirements/Fixtures/EmptyProject');
        $credential = JsonFile\to_array(root() . 'credentials.json');
        github_token($credential[GITHUB_DOMAIN]['token']);
        rename(root() . 'credentials.json', root() . 'credentials.json.back');
    },
    after: function () {
        reset_empty_project();
        rename(root() . 'credentials.json.back', root() . 'credentials.json');
    }
);
