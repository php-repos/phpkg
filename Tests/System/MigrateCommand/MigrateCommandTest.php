<?php

namespace Tests\System\MigrateCommand\MigrateCommandTest;

use PhpRepos\Cli\IO\Write;
use function PhpRepos\FileManager\Directory\delete_recursive;
use function PhpRepos\FileManager\Directory\make;
use function PhpRepos\FileManager\File\delete;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should show error messages when there is a Packages directory',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg migrate --project=TestRequirements/Fixtures/composer-package');

        Write\assert_error('There is a Packages directory in your project.', $output);
    },
    before: function () {
        make(root() . 'TestRequirements/Fixtures/composer-package/Packages');
    },
    after: function () {
        delete_recursive(root() . 'TestRequirements/Fixtures/composer-package/Packages');
    }
);

test(
    title: 'it should migrate symfony package',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg migrate --project=TestRequirements/Fixtures/composer-package');

        assert_correct_config_file('Config file is not correct!' . $output);
        assert_correct_meta_file('Meta file data is not correct!' . $output);
        assert_package_directory_content('Package directory content is not what we want!' . $output);
        Write\assert_success('Project migrated successfully.', $output);
    },
    after: function () {
        delete(realpath(root() . 'TestRequirements/Fixtures/composer-package/phpkg.config.json'));
        delete(realpath(root() . 'TestRequirements/Fixtures/composer-package/phpkg.config-lock.json'));
        delete_recursive(realpath(root() . 'TestRequirements/Fixtures/composer-package/Packages'));
    },
);

function assert_correct_config_file($message)
{
    $root = root() . 'TestRequirements/Fixtures/composer-package/';
    $stub = root() . 'TestRequirements/Stubs/composer-package/';

    assert_true((
            file_exists(realpath($root . 'phpkg.config.json'))
            && file_get_contents(realpath($root . 'phpkg.config.json')) === file_get_contents(realpath($stub . 'phpkg.config.json.stub'))
        ),
        $message
    );
}

function assert_correct_meta_file($message)
{
    $root = root() . 'TestRequirements/Fixtures/composer-package/';
    $stub = root() . 'TestRequirements/Stubs/composer-package/';

    assert_true((
            file_exists(realpath($root . 'phpkg.config-lock.json'))
            && file_get_contents(realpath($root . 'phpkg.config-lock.json')) === file_get_contents(realpath($stub . 'phpkg.config-lock.json.stub'))
        ),
        $message
    );
}

function assert_package_directory_content($message)
{
    $root = root() . 'TestRequirements/Fixtures/composer-package/';
    $stub = root() . 'TestRequirements/Stubs/composer-package/';

    assert_true((
            file_exists(realpath($root . 'Packages'))
            && file_exists(realpath($root . 'Packages/Seldaek'))
            && file_exists(realpath($root . 'Packages/Seldaek/monolog'))
            && file_exists(realpath($root . 'Packages/Seldaek/monolog/composer.json'))
            && file_exists(realpath($root . 'Packages/Seldaek/monolog/phpkg.config.json'))
            && file_exists(realpath($root . 'Packages/Seldaek/monolog/phpkg.config-lock.json'))
            && file_exists(realpath($root . 'Packages/php-fig'))
            && file_exists(realpath($root . 'Packages/php-fig/log'))
            && file_exists(realpath($root . 'Packages/php-fig/log/composer.json'))
            && file_exists(realpath($root . 'Packages/php-fig/log/phpkg.config.json'))
            && file_exists(realpath($root . 'Packages/php-fig/log/phpkg.config-lock.json'))
            && file_exists(realpath($root . 'Packages/symfony'))
            && file_exists(realpath($root . 'Packages/symfony/thanks'))
            && file_exists(realpath($root . 'Packages/symfony/thanks/composer.json'))
            && file_exists(realpath($root . 'Packages/symfony/thanks/phpkg.config.json'))
            && file_exists(realpath($root . 'Packages/symfony/thanks/phpkg.config-lock.json'))
            && file_get_contents(realpath($root . 'Packages/Seldaek/monolog/phpkg.config.json')) === file_get_contents(realpath($stub . 'monolog-phpkg.config.json.stub'))
            && file_get_contents(realpath($root . 'Packages/Seldaek/monolog/phpkg.config-lock.json')) === file_get_contents(realpath($stub . 'monolog-phpkg.config-lock.json.stub'))
            && file_get_contents(realpath($root . 'Packages/php-fig/log/phpkg.config.json')) === file_get_contents(realpath($stub . 'log-phpkg.config.json.stub'))
            && file_get_contents(realpath($root . 'Packages/php-fig/log/phpkg.config-lock.json')) === file_get_contents(realpath($stub . 'log-phpkg.config-lock.json.stub'))
            && file_get_contents(realpath($root . 'Packages/symfony/thanks/phpkg.config.json')) === file_get_contents(realpath($stub . 'symfony-thanks-phpkg.config.json.stub'))
            && file_get_contents(realpath($root . 'Packages/symfony/thanks/phpkg.config-lock.json')) === file_get_contents(realpath($stub . 'symfony-thanks-phpkg.config-lock.json.stub'))
        ),
        $message
    );
}
