<?php

namespace Tests\System\RemoveCommand\RemoveShareDependencyTest;

use PhpRepos\FileManager\JsonFile;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_empty_project;

test(
    title: 'it should remove the package but leave used subpackage',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg remove git@github.com:php-repos/complex-package.git --project=TestRequirements/Fixtures/EmptyProject');

        assert_desired_data_in_packages_directory('Package has not been deleted from Packages directory!' . $output);
        assert_config_file_is_clean('Packages has not been deleted from config file!' . $output);
        assert_meta_is_clean('Packages has not been deleted from meta file!' . $output);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/simple-package.git --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/complex-package.git --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        reset_empty_project();
    }
);

function assert_desired_data_in_packages_directory($message)
{
    clearstatcache();
    assert_true(
        file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages/php-repos/simple-package'))
        && ! file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages/php-repos/complex-package')),
        $message
    );
}

function assert_config_file_is_clean($message)
{
    $config = JsonFile\to_array(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json'));

    assert_true(
        isset($config['packages']['git@github.com:php-repos/simple-package.git'])
        && ! isset($config['packages']['git@github.com:php-repos/complex-package.git']),
        $message
    );
}

function assert_meta_is_clean($message)
{
    $config = JsonFile\to_array(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config-lock.json'));

    assert_true(isset($config['packages']['git@github.com:php-repos/simple-package.git']), $message);
    assert_true(! isset($config['packages']['git@github.com:php-repos/complex-package.git']), $message);
}
