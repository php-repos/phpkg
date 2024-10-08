<?php

namespace Tests\System\RemoveCommand\RemoveCommandTest;

use PhpRepos\FileManager\JsonFile;
use function PhpRepos\Cli\Output\assert_error;
use function PhpRepos\Cli\Output\assert_line;
use function PhpRepos\Cli\Output\assert_success;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\TestRunner\Assertions\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_dummy_project;

test(
    title: 'it should show error message when the project is not initialized',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg remove git@github.com:php-repos/simple-package.git --project=../../DummyProject');

        $lines = explode("\n", trim($output));

        assert_true(2 === count($lines), 'Number of output lines do not match' . $output);
        assert_line("Removing package git@github.com:php-repos/simple-package.git", $lines[0] . PHP_EOL);
        assert_error("Project is not initialized. Please try to initialize using the init command.", $lines[1] . PHP_EOL);
    }
);

test(
    title: 'it should remove a package',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg remove git@github.com:php-repos/complex-package.git --project=../../DummyProject');

        assert_success_output($output);
        assert_desired_data_in_packages_directory('Package has not been deleted from Packages directory!' . $output);
        assert_config_file_is_clean('Packages has not been deleted from config file!' . $output);
        assert_meta_is_clean('Packages has not been deleted from meta!' . $output);

        $output = shell_exec('php ' . root() . 'phpkg remove git@github.com:php-repos/complex-package.git --project=../../DummyProject');

        assert_error_output($output);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/complex-package.git --project=../../DummyProject');
    },
    after: function () {
        reset_dummy_project();
    }
);

function assert_success_output($output)
{
    $lines = explode("\n", trim($output));

    assert_true(5 === count($lines), 'Number of output lines do not match' . $output);
    assert_line("Removing package git@github.com:php-repos/complex-package.git", $lines[0] . PHP_EOL);
    assert_line("Finding package in configs...", $lines[1] . PHP_EOL);
    assert_line("Removing package from config...", $lines[2] . PHP_EOL);
    assert_line("Committing configs...", $lines[3] . PHP_EOL);
    assert_success("Package git@github.com:php-repos/complex-package.git has been removed successfully.", $lines[4] . PHP_EOL);
}

function assert_error_output($output)
{
    $lines = explode("\n", trim($output));

    assert_true(3 === count($lines), 'Number of output lines do not match' . $output);
    assert_line("Removing package git@github.com:php-repos/complex-package.git", $lines[0] . PHP_EOL);
    assert_line("Finding package in configs...", $lines[1] . PHP_EOL);
    assert_error("Package git@github.com:php-repos/complex-package.git does not found in your project!", $lines[2] . PHP_EOL);
}

function assert_desired_data_in_packages_directory($message)
{
    clearstatcache();
    assert_true((
            ! file_exists(realpath(root() . '../../DummyProject/Packages/php-repos/simple-package'))
            && ! file_exists(realpath(root() . '../../DummyProject/Packages/php-repos/complex-package'))
        ),
        $message
    );
}

function assert_config_file_is_clean($message)
{
    $config = JsonFile\to_array(realpath(root() . '../../DummyProject/phpkg.config.json'));

    assert_true($config['packages'] === [], $message);
}

function assert_meta_is_clean($message)
{
    $config = JsonFile\to_array(realpath(root() . '../../DummyProject/phpkg.config-lock.json'));

    assert_true($config['packages'] === [], $message);
}
