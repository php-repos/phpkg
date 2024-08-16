<?php

namespace Tests\System\AddCommand\AddReleasedPackageWithSpecificVersionTest;

use PhpRepos\FileManager\JsonFile;
use function PhpRepos\Cli\Output\assert_error;
use function PhpRepos\Cli\Output\assert_line;
use function PhpRepos\Cli\Output\assert_success;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\TestRunner\Assertions\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_empty_project;

test(
    title: 'it should add released package to the project with the given version',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/released-package.git --version=v1.0.1 --project=TestRequirements/Fixtures/EmptyProject');

        $lines = explode("\n", trim($output));

        assert_true(7 === count($lines), 'Number of output lines do not match' . $output);
        assert_line("Adding package git@github.com:php-repos/released-package.git version v1.0.1...", $lines[0] . PHP_EOL);
        assert_line("Checking installed packages...", $lines[1] . PHP_EOL);
        assert_line("Setting package version...", $lines[2] . PHP_EOL);
        assert_line("Adding the package...", $lines[3] . PHP_EOL);
        assert_line("Updating configs...", $lines[4] . PHP_EOL);
        assert_line("Committing configs...", $lines[5] . PHP_EOL);
        assert_success("Package git@github.com:php-repos/released-package.git has been added successfully.", $lines[6] . PHP_EOL);

        assert_config_file_created_for_released_project('Config file is not created!' . $output);
        assert_released_package_added_to_config('v1.0.1', 'Released Package does not added to config file properly! ' . $output);
        assert_packages_directory_created_for_empty_project('Package directory does not created.' . $output);
        assert_released_package_cloned('Released package does not cloned!' . $output);
        assert_meta_has_desired_data_for_v1_0_1('Meta data is not what we want.' . $output);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'it should add released package to the project with the given portion of the version',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/released-package.git --version=1 --project=TestRequirements/Fixtures/EmptyProject');

        $lines = explode("\n", trim($output));

        assert_true(7 === count($lines), 'Number of output lines do not match' . $output);
        assert_line("Adding package git@github.com:php-repos/released-package.git version 1...", $lines[0] . PHP_EOL);
        assert_line("Checking installed packages...", $lines[1] . PHP_EOL);
        assert_line("Setting package version...", $lines[2] . PHP_EOL);
        assert_line("Adding the package...", $lines[3] . PHP_EOL);
        assert_line("Updating configs...", $lines[4] . PHP_EOL);
        assert_line("Committing configs...", $lines[5] . PHP_EOL);
        assert_success("Package git@github.com:php-repos/released-package.git has been added successfully.", $lines[6] . PHP_EOL);

        assert_config_file_created_for_released_project('Config file is not created!' . $output);
        assert_released_package_added_to_config('v1.1.0', 'Released Package does not added to config file properly! ' . $output);
        assert_packages_directory_created_for_empty_project('Package directory does not created.' . $output);
        assert_released_package_cloned('Released package does not cloned!' . $output);
        assert_meta_has_desired_data_for_v1_1_0('Meta data is not what we want.' . $output);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        reset_empty_project();
    }
);

test(
    title: 'it should show error message when can not detect the version',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/released-package.git --version=10 --project=TestRequirements/Fixtures/EmptyProject');

        $lines = explode("\n", trim($output));

        assert_true(4 === count($lines), 'Number of output lines do not match' . $output);
        assert_line("Adding package git@github.com:php-repos/released-package.git version 10...", $lines[0] . PHP_EOL);
        assert_line("Checking installed packages...", $lines[1] . PHP_EOL);
        assert_line("Setting package version...", $lines[2] . PHP_EOL);
        assert_error("Can not find 10 for package php-repos/released-package.", $lines[3] . PHP_EOL);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
    },
    after: function () {
        reset_empty_project();
    }
);

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

function assert_released_package_added_to_config($version, $message)
{
    $config = JsonFile\to_array(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config.json'));

    assert_true((
            isset($config['packages']['git@github.com:php-repos/released-package.git'])
            && $version === $config['packages']['git@github.com:php-repos/released-package.git']
        ),
        $message
    );
}

function assert_meta_has_desired_data_for_v1_0_1($message)
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

function assert_meta_has_desired_data_for_v1_1_0($message)
{
    $meta = JsonFile\to_array(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/phpkg.config-lock.json'));

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
