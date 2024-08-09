<?php

namespace Tests\System\UpdateCommand\UpdateToSpecificVersionTest;

use PhpRepos\FileManager\JsonFile;
use function PhpRepos\Cli\Output\assert_line;
use function PhpRepos\Cli\Output\assert_success;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_empty_project;

test(
    title: 'it should update to the given version',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg update git@github.com:php-repos/released-package.git --version=v1.0.1 --project=TestRequirements/Fixtures/EmptyProject');

        $lines = explode("\n", trim($output));

        assert_true(7 === count($lines), 'Number of output lines do not match' . $output);
        assert_line("Updating package git@github.com:php-repos/released-package.git to version v1.0.1...", $lines[0] . PHP_EOL);
        assert_line("Finding package in configs...", $lines[1] . PHP_EOL);
        assert_line("Setting package version...", $lines[2] . PHP_EOL);
        assert_line("Updating package...", $lines[3] . PHP_EOL);
        assert_line("Updating configs...", $lines[4] . PHP_EOL);
        assert_line("Committing new configs...", $lines[5] . PHP_EOL);
        assert_success("Package git@github.com:php-repos/released-package.git has been updated.", $lines[6] . PHP_EOL);

        assert_given_version_added('Package did not updated to given package version. ' . $output);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/released-package.git --version=v1.0.0 --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        reset_empty_project();
    }
);

function assert_given_version_added($message)
{
    $config = JsonFile\to_array(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json'));
    $meta = JsonFile\to_array(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config-lock.json'));

    assert_true(
        isset($config['packages']['git@github.com:php-repos/released-package.git'])
        && 'v1.0.1' === $config['packages']['git@github.com:php-repos/released-package.git']
        && isset($meta['packages']['git@github.com:php-repos/released-package.git'])
        && 'v1.0.1' === $meta['packages']['git@github.com:php-repos/released-package.git']['version']
        && 'php-repos' === $meta['packages']['git@github.com:php-repos/released-package.git']['owner']
        && 'released-package' === $meta['packages']['git@github.com:php-repos/released-package.git']['repo']
        && '34c23761155364826342a79766b6d662aa0ae7fb' === $meta['packages']['git@github.com:php-repos/released-package.git']['hash'],
        $message
    );
}
