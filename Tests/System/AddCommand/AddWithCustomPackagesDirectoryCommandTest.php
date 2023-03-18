<?php

namespace Tests\System\AddCommand\AddWithCustomPackagesDirectoryCommandTest;

use PhpRepos\FileManager\JsonFile;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_empty_project;

test(
    title: 'it should add package to the given directory',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/simple-package.git --project=TestRequirements/Fixtures/EmptyProject');

        assert_package_directory_added_to_config('Config does not contains the custom package directory!');
        assert_config_file_created_for_simple_project('Config file is not created!' . $output);
        assert_simple_package_added_to_config('Simple Package does not added to config file properly! ' . $output);
        assert_packages_directory_created_for_empty_project('Package directory does not created.' . $output);
        assert_simple_package_cloned('Simple package does not cloned!' . $output);
        assert_meta_has_desired_data('Meta data is not what we want.' . $output);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject --packages-directory=vendor');
    },
    after: function () {
        reset_empty_project();
    }
);

function assert_package_directory_added_to_config($message)
{
    $config = JsonFile\to_array(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json'));

    assert_true(
        $config['packages-directory'] === 'vendor',
        $message
    );
}

function assert_config_file_created_for_simple_project($message)
{
    assert_true(file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json')), $message);
}

function assert_packages_directory_created_for_empty_project($message)
{
    assert_true(file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/vendor')), $message);
}

function assert_simple_package_cloned($message)
{
    assert_true(
        file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/vendor/php-repos/simple-package'))
        && file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/vendor/php-repos/simple-package/phpkg.config.json'))
        && file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/vendor/php-repos/simple-package/README.md')),
        $message
    );
}

function assert_simple_package_added_to_config($message)
{
    $config = JsonFile\to_array(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json'));

    assert_true((
            isset($config['packages']['git@github.com:php-repos/simple-package.git'])
            && 'development' === $config['packages']['git@github.com:php-repos/simple-package.git']
        ),
        $message
    );
}

function assert_meta_has_desired_data($message)
{
    $meta = JsonFile\to_array(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config-lock.json'));

    assert_true((
            isset($meta['packages']['git@github.com:php-repos/simple-package.git'])
            && 'development' === $meta['packages']['git@github.com:php-repos/simple-package.git']['version']
            && 'php-repos' === $meta['packages']['git@github.com:php-repos/simple-package.git']['owner']
            && 'simple-package' === $meta['packages']['git@github.com:php-repos/simple-package.git']['repo']
            && '1022f2004a8543326a92c0ba606439db530a23c9' === $meta['packages']['git@github.com:php-repos/simple-package.git']['hash']
        ),
        $message
    );
}
