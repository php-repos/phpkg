<?php

namespace Tests\System\BuildCommand\BuildWithoutConfigFilesTest;

use function PhpRepos\Cli\Output\assert_success;
use function PhpRepos\Datatype\Arr\last;
use function PhpRepos\FileManager\File\delete;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\force_delete;

test(
    title: 'it should build project without config files',
    case: function () {
        exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/ProjectWithTests', $output);

        assert_success('Build finished successfully.', last($output) . PHP_EOL);
    },
    before: function () {
        copy(
            realpath(root() . 'TestRequirements/Stubs/ProjectWithTests/phpkg.config.json'),
            realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/phpkg.config.json')
        );
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/simple-package.git --project=TestRequirements/Fixtures/ProjectWithTests');
        delete(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/Packages/php-repos/simple-package/phpkg.config.json'));
    },
    after: function () {
        delete_build_directory();
        delete_packages_directory();
        delete(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/phpkg.config.json'));
        delete(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/phpkg.config-lock.json'));
    }
);

function delete_build_directory()
{
    force_delete(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/builds'));
}

function delete_packages_directory()
{
    force_delete(realpath(root() . 'TestRequirements/Fixtures/ProjectWithTests/Packages'));
}
