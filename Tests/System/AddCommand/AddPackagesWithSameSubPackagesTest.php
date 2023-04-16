<?php

namespace Tests\System\AddCommand\AddPackagesWithSameSubPackagesTest;

use PhpRepos\FileManager\JsonFile;
use function PhpRepos\Cli\IO\Write\assert_line;
use function PhpRepos\Cli\IO\Write\assert_success;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_empty_project;

test(
    title: 'it should not stuck if two packages using the same dependencies',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/cli.git --project=TestRequirements/Fixtures/EmptyProject');

        assert_output($output);
        assert_true(file_exists(root() . 'TestRequirements/Fixtures/EmptyProject/Packages/php-repos/cli'));
        assert_true(file_exists(root() . 'TestRequirements/Fixtures/EmptyProject/Packages/php-repos/test-runner'));
        $config = JsonFile\to_array(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json');
        assert_true((
                isset($config['packages']['git@github.com:php-repos/test-runner.git'])
                && isset($config['packages']['git@github.com:php-repos/cli.git'])
            ),
            'Config file has not been created properly.'
        );
        $meta = JsonFile\to_array(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config-lock.json');
        assert_true(4 === count($meta['packages']), 'Count of packages in meta file is not correct.');
        assert_true((
                array_key_exists('git@github.com:php-repos/test-runner.git', $meta['packages'])
                && array_key_exists('https://github.com/php-repos/cli.git', $meta['packages'])
            ),
            'Meta file has not been created properly.'
        );
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/test-runner.git --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        reset_empty_project();
    }
);

function assert_output($output)
{
    $lines = explode("\n", trim($output));

    assert_true(11 === count($lines), 'Number of output lines do not match' . $output);
    assert_line("Adding package git@github.com:php-repos/cli.git latest version...", $lines[0] . PHP_EOL);
    assert_line("Setting env credential...", $lines[1] . PHP_EOL);
    assert_line("Loading configs...", $lines[2] . PHP_EOL);
    assert_line("Checking installed packages...", $lines[3] . PHP_EOL);
    assert_line("Setting package version...", $lines[4] . PHP_EOL);
    assert_line("Creating package directory...", $lines[5] . PHP_EOL);
    assert_line("Detecting version hash...", $lines[6] . PHP_EOL);
    assert_line("Downloading the package...", $lines[7] . PHP_EOL);
    assert_line("Updating configs...", $lines[8] . PHP_EOL);
    assert_line("Committing configs...", $lines[9] . PHP_EOL);
    assert_success("Package git@github.com:php-repos/cli.git has been added successfully.", $lines[10] . PHP_EOL);
}
