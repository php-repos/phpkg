<?php

namespace Tests\System\MigrateCommandTest\MigrateFromComposerTest;

use PhpRepos\FileManager\Filesystem\File;
use PhpRepos\FileManager\FileType\Json;
use function PhpRepos\Cli\IO\Write\assert_success;
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
        File::from_string(root() . '/TestRequirements/Fixtures/composer-package/phpkg.config.json')->delete();
        File::from_string(root() . '/TestRequirements/Fixtures/composer-package/phpkg.config-lock.json')->delete();
    }
);

function assert_config_file()
{
    assert_true(
        Json\to_array(root() . '/TestRequirements/Fixtures/composer-package/phpkg.config.json')
        ===
        Json\to_array(root() . '/TestRequirements/Stubs/composer-package/phpkg.config.json.stub'),
        'Config file is not correct.'
    );
}

function assert_config_lock_file()
{
    assert_true(
        Json\to_array(root() . '/TestRequirements/Fixtures/composer-package/phpkg.config-lock.json')
        ===
        Json\to_array(root() . '/TestRequirements/Stubs/composer-package/phpkg.config-lock.json.stub'),
        'Config file is not correct.'
    );
}
