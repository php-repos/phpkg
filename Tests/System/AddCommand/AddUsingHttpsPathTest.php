<?php

namespace Tests\System\AddCommand\AddUsingHttpsPath;

use PhpRepos\FileManager\JsonFile;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\TestRunner\Assertions\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_dummy_project;

test(
    title: 'it should add package to the project using https url',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg add https://github.com/php-repos/cli.git --version=v1.0.0 --project=../../DummyProject');

        assert_config_file_created_for_http_project('Config file is not created!' . $output);
        assert_http_package_added_to_config('Http Package does not added to config file properly! ' . $output);
        assert_packages_directory_created_for_empty_project('Package directory does not created.' . $output);
        assert_http_package_cloned('Http package does not cloned!' . $output);
        assert_meta_has_desired_data('Meta data is not what we want.' . $output);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=../../DummyProject');
    },
    after: function () {
        reset_dummy_project();
    }
);

test(
    title: 'it should add package to the project without trailing .git',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg add https://github.com/php-repos/cli --version=v1.0.0 --project=../../DummyProject');

        assert_http_package_cloned('Http package does not cloned!' . $output);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=../../DummyProject');
    },
    after: function () {
        reset_dummy_project();
    }
);

function assert_config_file_created_for_http_project($message)
{
    assert_true(file_exists(realpath(root() . '../../DummyProject/phpkg.config.json')), $message);
}

function assert_packages_directory_created_for_empty_project($message)
{
    assert_true(file_exists(realpath(root() . '../../DummyProject/Packages')), $message);
}

function assert_http_package_cloned($message)
{
    assert_true(
        file_exists(realpath(root() . '../../DummyProject/Packages/php-repos/cli'))
        && file_exists(realpath(root() . '../../DummyProject/Packages/php-repos/cli/phpkg.config.json'))
        && file_exists(realpath(root() . '../../DummyProject/Packages/php-repos/cli/phpkg.config-lock.json')),
        $message
    );
}

function assert_http_package_added_to_config($message)
{
    $config = JsonFile\to_array(realpath(root() . '../../DummyProject/phpkg.config.json'));

    assert_true((
            isset($config['packages']['https://github.com/php-repos/cli.git'])
            && 'v1.0.0' === $config['packages']['https://github.com/php-repos/cli.git']
        ),
        $message
    );
}

function assert_meta_has_desired_data($message)
{
    $meta = JsonFile\to_array(realpath(root() . '../../DummyProject/phpkg.config-lock.json'));

    assert_true((
            isset($meta['packages']['https://github.com/php-repos/cli.git'])
            && 'v1.0.0' === $meta['packages']['https://github.com/php-repos/cli.git']['version']
            && 'php-repos' === $meta['packages']['https://github.com/php-repos/cli.git']['owner']
            && 'cli' === $meta['packages']['https://github.com/php-repos/cli.git']['repo']
            && '9d8bd24f9d31b5bf18bc01e89053d311495f714d' === $meta['packages']['https://github.com/php-repos/cli.git']['hash']
        ),
        $message
    );
}
