<?php

namespace Tests\System\BuildCommand\BuildWithoutConfigFilesTest;

use function PhpRepos\Cli\Output\assert_success;
use function PhpRepos\Datatype\Arr\last;
use function PhpRepos\FileManager\File\delete;
use function PhpRepos\FileManager\Resolver\root;
use function PhpRepos\FileManager\Resolver\realpath;
use function PhpRepos\TestRunner\Runner\test;
use function Tests\Helper\reset_empty_project;

test(
    title: 'it should build project without config files',
    case: function () {
        exec('php ' . root() . 'phpkg build --project=TestRequirements/Fixtures/EmptyProject', $output);

        assert_success('Build finished successfully.', last($output) . PHP_EOL);
    },
    before: function () {
        shell_exec('php ' . root() . 'phpkg init --project=TestRequirements/Fixtures/EmptyProject');
        shell_exec('php ' . root() . 'phpkg add git@github.com:php-repos/simple-package.git --project=TestRequirements/Fixtures/EmptyProject');
        delete(realpath(root() . 'TestRequirements/Fixtures/EmptyProject/Packages/php-repos/simple-package/phpkg.config.json'));
    },
    after: function () {
        reset_empty_project();
    }
);
