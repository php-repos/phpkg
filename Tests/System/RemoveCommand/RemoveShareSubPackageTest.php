<?php

namespace Tests\System\RemoveCommand\RemoveShareSubPackageTest;

use PhpRepos\FileManager\JsonFile;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_dummy_project;

test(
    title: 'it should not delete package used in another package as sub package',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg remove git@github.com:php-repos/test-runner.git --project=../../DummyProject');

        assert_desired_data_in_packages_directory('Package has been deleted from Packages directory!' . $output);
        assert_config_file_is_clean('Packages has not been deleted from config file!' . $output);
        assert_meta_is_clean('Packages has been deleted from meta file!' . $output);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/test-runner.git --version=v1 --project=../../DummyProject');
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/cli.git --version=v2 --project=../../DummyProject');
    },
    after: function () {
        reset_dummy_project();
    }
);

function assert_desired_data_in_packages_directory($message)
{
    clearstatcache();
    assert_true((
        file_exists(realpath(root() . '../../DummyProject/Packages/php-repos/cli'))
        && file_exists(realpath(root() . '../../DummyProject/Packages/php-repos/test-runner'))
    ),
        $message
    );
}

function assert_config_file_is_clean($message)
{
    $config = JsonFile\to_array(realpath(root() . '../../DummyProject/phpkg.config.json'));

    assert_true(
        isset($config['packages']['git@github.com:php-repos/cli.git'])
        && ! isset($config['packages']['git@github.com:php-repos/test-runner.git']),
        $message
    );
}

function assert_meta_is_clean($message)
{
    $config = JsonFile\to_array(realpath(root() . '../../DummyProject/phpkg.config-lock.json'));

    assert_true(isset($config['packages']['https://github.com/php-repos/cli.git']), $message . ' Cli package not found in the lock file.');
    assert_true(isset($config['packages']['git@github.com:php-repos/test-runner.git']), $message . ' Test runner package not found in the lock file.');
}
