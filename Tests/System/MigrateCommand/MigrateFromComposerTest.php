<?php

namespace Tests\System\MigrateCommandTest\MigrateFromComposerTest;

use PhpRepos\FileManager\JsonFile;
use PhpRepos\FileManager\Path;
use function PhpRepos\Cli\IO\Write\assert_success;
use function PhpRepos\FileManager\File\delete;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\TestRunner\Assertions\Boolean\assert_true;
use function PhpRepos\TestRunner\Runner\test;

test(
    title: 'it should make a config file for migrate from composer',
    case: function () {
        $output = shell_exec('php ' . root() . 'phpkg migrate --project=TestRequirements/Fixtures/composer-package');

        assert_success('Migration has been finished successfully.', $output);
        assert_config_file();
        assert_config_lock_file();
    },
    after: function () {
        delete(Path::from_string(root() . '/TestRequirements/Fixtures/composer-package/phpkg.config.json'));
        delete(Path::from_string(root() . '/TestRequirements/Fixtures/composer-package/phpkg.config-lock.json'));
    }
);

function assert_config_file()
{
    assert_true(
        JsonFile\to_array(root() . '/TestRequirements/Fixtures/composer-package/phpkg.config.json')
        ===
        JsonFile\to_array(root() . '/TestRequirements/Stubs/composer-package/phpkg.config.json.stub'),
        'Config file is not correct.'
    );
}

function assert_config_lock_file()
{
    assert_true(
        JsonFile\to_array(root() . '/TestRequirements/Fixtures/composer-package/phpkg.config-lock.json')
        ===
        JsonFile\to_array(root() . '/TestRequirements/Stubs/composer-package/phpkg.config-lock.json.stub'),
        'Config file is not correct.'
    );
}
