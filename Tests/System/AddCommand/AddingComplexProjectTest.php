<?php

namespace Tests\System\AddCommand\AddingComplexProjectTest;

use PhpRepos\FileManager\JsonFile;
use function PhpRepos\FileManager\File\delete;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\force_delete;

test(
    title: 'it should add a complex project',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/complex-package.git --project=TestRequirements/Fixtures/ProjectWithTests');

        assert_packages_added_to_packages_directory('Packages does not added to the packages directory!' . $output);
        assert_config_file_has_desired_data('Config file for adding complex package does not have desired data!' . $output);
        assert_meta_file_has_desired_data('Meta data for adding complex package does not have desired data!' . $output);
    },
    before: function () {
        copy(
            realpath(root() . 'TestRequirements/Stubs/ProjectWithTests/phpkg.config.json'),
            realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/phpkg.config.json')
        );
    },
    after: function () {
        delete_config_file();
        delete_meta_file();
        delete_packages_directory();
    }
);

test(
    title: 'it should add a complex project with http path',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg add https://github.com/php-repos/complex-package.git --project=TestRequirements/Fixtures/ProjectWithTests');

        assert_packages_added_to_packages_directory('Packages does not added to the packages directory!' . $output);
    },
    before: function () {
        copy(
            realpath(root() . 'TestRequirements/Stubs/ProjectWithTests/phpkg.config.json'),
            realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/phpkg.config.json')
        );
    },
    after: function () {
        delete_config_file();
        delete_meta_file();
        delete_packages_directory();
    }
);

function delete_config_file()
{
    delete(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/phpkg.config.json'));
}

function delete_meta_file()
{
    delete(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/phpkg.config-lock.json'));
}

function delete_packages_directory()
{
    force_delete(root() . 'TestRequirements/Fixtures/ProjectWithTests/Packages');
}

function assert_packages_added_to_packages_directory($message)
{
    assert_true((
            file_exists(root() . 'TestRequirements/Fixtures/ProjectWithTests/Packages/php-repos/simple-package')
            && file_exists(root() . 'TestRequirements/Fixtures/ProjectWithTests/Packages/php-repos/simple-package/phpkg.config.json')
            && file_exists(root() . 'TestRequirements/Fixtures/ProjectWithTests/Packages/php-repos/simple-package/README.md')
            && file_exists(root() . 'TestRequirements/Fixtures/ProjectWithTests/Packages/php-repos/complex-package')
            && file_exists(root() . 'TestRequirements/Fixtures/ProjectWithTests/Packages/php-repos/complex-package/phpkg.config.json')
            && file_exists(root() . 'TestRequirements/Fixtures/ProjectWithTests/Packages/php-repos/complex-package/phpkg.config-lock.json')
        ),
        $message
    );
}

function assert_config_file_has_desired_data($message)
{
    $config = JsonFile\to_array(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/phpkg.config.json'));

    assert_true((
            ! isset($config['packages']['git@github.com:php-repos/simple-package.git'])
            && isset($config['packages']['git@github.com:php-repos/complex-package.git'])
            && 'development' === $config['packages']['git@github.com:php-repos/complex-package.git']
        ),
        $message
    );
}

function assert_meta_file_has_desired_data($message)
{
    $meta = JsonFile\to_array(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/phpkg.config-lock.json'));

    assert_true((
            isset($meta['packages']['git@github.com:php-repos/simple-package.git'])
            && 'development' === $meta['packages']['git@github.com:php-repos/simple-package.git']['version']
            && 'php-repos' === $meta['packages']['git@github.com:php-repos/simple-package.git']['owner']
            && 'simple-package' === $meta['packages']['git@github.com:php-repos/simple-package.git']['repo']
            && '1022f2004a8543326a92c0ba606439db530a23c9' === $meta['packages']['git@github.com:php-repos/simple-package.git']['hash']

            && isset($meta['packages']['git@github.com:php-repos/complex-package.git'])
            && 'development' === $meta['packages']['git@github.com:php-repos/complex-package.git']['version']
            && 'php-repos' === $meta['packages']['git@github.com:php-repos/complex-package.git']['owner']
            && 'complex-package' === $meta['packages']['git@github.com:php-repos/complex-package.git']['repo']
            && '079acc5267e34016e3aa0b70cc1291edeb032d03' === $meta['packages']['git@github.com:php-repos/complex-package.git']['hash']
        ),
        $message
    );
}
