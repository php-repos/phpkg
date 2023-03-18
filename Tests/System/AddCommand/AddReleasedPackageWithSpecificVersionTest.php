<?php

namespace Tests\System\AddCommand\AddReleasedPackageWithSpecificVersionTest;

use PhpRepos\FileManager\JsonFile;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_empty_project;

test(
    title: 'it should add released package to the project with the given version',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/released-package.git --version=v1.0.1 --project=TestRequirements/Fixtures/EmptyProject');

        assert_output($output);
        assert_config_file_created_for_released_project('Config file is not created!' . $output);
        assert_released_package_added_to_config('Released Package does not added to config file properly! ' . $output);
        assert_packages_directory_created_for_empty_project('Package directory does not created.' . $output);
        assert_released_package_cloned('Released package does not cloned!' . $output);
        assert_meta_has_desired_data('Meta data is not what we want.' . $output);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        reset_empty_project();
    }
);

function assert_output($output)
{
    $expected = <<<EOD
\e[39mAdding package git@github.com:php-repos/released-package.git version v1.0.1...
\e[39mSetting env credential...
\e[39mLoading configs...
\e[39mChecking installed packages...
\e[39mSetting package version...
\e[39mCreating package directory...
\e[39mDetecting version hash...
\e[39mDownloading the package...
\e[39mUpdating configs...
\e[39mCommitting configs...
\e[92mPackage git@github.com:php-repos/released-package.git has been added successfully.\e[39m

EOD;

    assert_true($expected === $output, 'Output is not correct:' . PHP_EOL . $expected . PHP_EOL . $output);
}

function assert_config_file_created_for_released_project($message)
{
    assert_true(file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json')), $message);
}

function assert_packages_directory_created_for_empty_project($message)
{
    assert_true(file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages')), $message);
}

function assert_released_package_cloned($message)
{
    assert_true(
        file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages/php-repos/released-package'))
        && file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages/php-repos/released-package/phpkg.config.json'))
        && file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages/php-repos/released-package/phpkg.config-lock.json'))
        && ! file_exists(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages/php-repos/released-package/Tests')),
        $message
    );
}

function assert_released_package_added_to_config($message)
{
    $config = JsonFile\to_array(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json'));

    assert_true((
            isset($config['packages']['git@github.com:php-repos/released-package.git'])
            && 'v1.0.1' === $config['packages']['git@github.com:php-repos/released-package.git']
        ),
        $message
    );
}

function assert_meta_has_desired_data($message)
{
    $meta = JsonFile\to_array(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config-lock.json'));

    assert_true((
            isset($meta['packages']['git@github.com:php-repos/released-package.git'])
            && 'v1.0.1' === $meta['packages']['git@github.com:php-repos/released-package.git']['version']
            && 'php-repos' === $meta['packages']['git@github.com:php-repos/released-package.git']['owner']
            && 'released-package' === $meta['packages']['git@github.com:php-repos/released-package.git']['repo']
            && '34c23761155364826342a79766b6d662aa0ae7fb' === $meta['packages']['git@github.com:php-repos/released-package.git']['hash']
        ),
        $message
    );
}
