<?php

namespace Tests\System\AddComand\AddReleasedPackageTest;

use PhpRepos\FileManager\JsonFile;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\TestRunner\Assertions\assert_true;
use function PhpRepos\TestRunner\Assertions\assert_false;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\CRLF_to_EOL;
use function Tests\Helper\reset_dummy_project;

test(
    title: 'it should add released package to the project',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/released-package.git --project=../../DummyProject');

        assert_config_file_created_for_released_project('Config file is not created!' . $output);
        assert_released_package_added_to_config('Released Package does not added to config file properly! ' . $output);
        assert_packages_directory_created_for_empty_project('Package directory does not created.' . $output);
        assert_released_package_cloned('Released package does not cloned!' . $output);
        assert_meta_has_desired_data('Meta data is not what we want.' . $output);
        assert_zip_file_deleted('Zip file has not been deleted.' . $output);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=../../DummyProject');
    },
    after: function () {
        reset_dummy_project();
    }
);

test(
    title: 'it should add development version of released package to the project if version passed as development',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/released-package.git --version=development --project=../../DummyProject');

        assert_development_branch_added('We expected to see development branch for the package! ' . $output);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=../../DummyProject');
    },
    after: function () {
        reset_dummy_project();
    }
);

function assert_development_branch_added($message)
{
    $meta = JsonFile\to_array(root() . '../../DummyProject/phpkg.config-lock.json');

    assert_true((
            isset($meta['packages']['git@github.com:php-repos/released-package.git'])
            && 'development' === $meta['packages']['git@github.com:php-repos/released-package.git']['version']
            && 'php-repos' === $meta['packages']['git@github.com:php-repos/released-package.git']['owner']
            && 'released-package' === $meta['packages']['git@github.com:php-repos/released-package.git']['repo']
            && str_contains(file_get_contents(root() . '../../DummyProject/Packages/php-repos/released-package/release-file.txt'), 'v1.1.0')
        ),
        $message
    );
}

function assert_config_file_created_for_released_project($message)
{
    assert_true(file_exists(realpath(root() . '../../DummyProject/phpkg.config.json')), $message);
}

function assert_packages_directory_created_for_empty_project($message)
{
    assert_true(file_exists(realpath(root() . '../../DummyProject/Packages')), $message);
}

function assert_released_package_cloned($message)
{
    assert_true((
            file_exists(realpath(root() . '../../DummyProject/Packages/php-repos/released-package'))
            && file_exists(realpath(root() . '../../DummyProject/Packages/php-repos/released-package/phpkg.config.json'))
            && file_exists(realpath(root() . '../../DummyProject/Packages/php-repos/released-package/phpkg.config-lock.json'))
            && file_exists(realpath(root() . '../../DummyProject/Packages/php-repos/released-package/release-file.txt'))
            && ! file_exists(realpath(root() . '../../DummyProject/Packages/php-repos/released-package/Tests'))
        ),
        $message
    );

    $sample_file = file_get_contents(realpath(root() . '../../DummyProject/Packages/php-repos/released-package/release-file.txt'));

    $expected_output = CRLF_to_EOL(<<<EOD
This is a specific file.
v1.0.0
v1.0.1
v1.1.0

EOD);

    assert_true($sample_file === $expected_output, 'release file content is not correct');
}

function assert_released_package_added_to_config($message)
{
    $config = JsonFile\to_array(realpath(root() . '../../DummyProject/phpkg.config.json'));

    assert_true(
        isset($config['packages']['git@github.com:php-repos/released-package.git'])
        && 'v1.1.0' === $config['packages']['git@github.com:php-repos/released-package.git'],
        $message
    );
}

function assert_meta_has_desired_data($message)
{
    $meta = JsonFile\to_array(realpath(root() . '../../DummyProject/phpkg.config-lock.json'));

    assert_true((
            isset($meta['packages']['git@github.com:php-repos/released-package.git'])
            && 'v1.1.0' === $meta['packages']['git@github.com:php-repos/released-package.git']['version']
            && 'php-repos' === $meta['packages']['git@github.com:php-repos/released-package.git']['owner']
            && 'released-package' === $meta['packages']['git@github.com:php-repos/released-package.git']['repo']
            && 'be24f45d8785c215901ba90b242f3b8a7d2bdbfb' === $meta['packages']['git@github.com:php-repos/released-package.git']['hash']
        ),
        $message
    );
}

function assert_zip_file_deleted($message)
{
    assert_false(
        file_exists(realpath(root() . '../../DummyProject/Packages/php-repos/released-package.zip')),
        $message
    );
}
