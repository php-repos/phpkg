<?php

namespace Tests\System\RemoveCommand\RemoveCommandTest;

use PhpRepos\FileManager\JsonFile;
use function PhpRepos\Cli\IO\Write\assert_error;
use function PhpRepos\Cli\IO\Write\assert_line;
use function PhpRepos\Cli\IO\Write\assert_success;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_empty_project;

test(
    title: 'it should show error message when the project is not initialized',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg remove git@github.com:php-repos/simple-package.git --project=TestRequirements/Fixtures/EmptyProject');

        $lines = explode("\n", trim($output));

        assert_true(2 === count($lines), 'Number of output lines do not match' . $output);
        assert_line("Removing package git@github.com:php-repos/simple-package.git", $lines[0] . PHP_EOL);
        assert_error("Project is not initialized. Please try to initialize using the init command.", $lines[1] . PHP_EOL);
    }
);

test(
    title: 'it should remove a package',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg remove git@github.com:php-repos/complex-package.git --project=TestRequirements/Fixtures/EmptyProject');

        assert_success_output($output);
        assert_desired_data_in_packages_directory('Package has not been deleted from Packages directory!' . $output);
        assert_config_file_is_clean('Packages has not been deleted from config file!' . $output);
        assert_meta_is_clean('Packages has not been deleted from meta!' . $output);

        $output = shell_exec('php ' . root() . 'phpkg remove git@github.com:php-repos/complex-package.git --project=TestRequirements/Fixtures/EmptyProject');

        assert_error_output($output);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/complex-package.git --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        reset_empty_project();
    }
);

function assert_success_output($output)
{
    $lines = explode("\n", trim($output));

    assert_true(7 === count($lines), 'Number of output lines do not match' . $output);
    assert_line("Removing package git@github.com:php-repos/complex-package.git", $lines[0] . PHP_EOL);
    assert_line("Loading configs...", $lines[1] . PHP_EOL);
    assert_line("Finding package in configs...", $lines[2] . PHP_EOL);
    assert_line("Loading package's config...", $lines[3] . PHP_EOL);
    assert_line("Removing package from config...", $lines[4] . PHP_EOL);
    assert_line("Committing configs...", $lines[5] . PHP_EOL);
    assert_success("Package git@github.com:php-repos/complex-package.git has been removed successfully.", $lines[6] . PHP_EOL);
}

function assert_error_output($output)
{
    $lines = explode("\n", trim($output));

    assert_true(4 === count($lines), 'Number of output lines do not match' . $output);
    assert_line("Removing package git@github.com:php-repos/complex-package.git", $lines[0] . PHP_EOL);
    assert_line("Loading configs...", $lines[1] . PHP_EOL);
    assert_line("Finding package in configs...", $lines[2] . PHP_EOL);
    assert_error("Package git@github.com:php-repos/complex-package.git does not found in your project!", $lines[3] . PHP_EOL);
}

function assert_desired_data_in_packages_directory($message)
{
    clearstatcache();
    assert_true((
            ! file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages/php-repos/simple-package'))
            && ! file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages/php-repos/complex-package'))
        ),
        $message
    );
}

function assert_config_file_is_clean($message)
{
    $config = JsonFile\to_array(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json'));

    assert_true($config['packages'] === [], $message);
}

function assert_meta_is_clean($message)
{
    $config = JsonFile\to_array(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config-lock.json'));

    assert_true($config['packages'] === [], $message);
}
